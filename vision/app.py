from pathlib import Path
from typing import List
import math

import cv2
import numpy as np

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel

app = FastAPI(
    title="Sell My Junk Vision",
    version="1.4"
)

DATA_ROOT = Path("/data").resolve()


class BoundingBox(BaseModel):
    x: float
    y: float
    width: float
    height: float


class ItemRegion(BaseModel):
    id: int
    title: str = ""
    bounding_box: BoundingBox


class RoiDebugRequest(BaseModel):
    path: str
    items: List[ItemRegion]


def resolve_storage_path(relative_path: str) -> Path:
    relative_path = relative_path.lstrip("/")

    candidate = (
        DATA_ROOT
        / "public"
        / relative_path
    ).resolve()

    if DATA_ROOT not in candidate.parents:
        raise HTTPException(
            status_code=400,
            detail="Invalid storage path"
        )

    if not candidate.is_file():
        raise HTTPException(
            status_code=404,
            detail=f"Image not found: {relative_path}"
        )

    return candidate


def order_points(
    points: np.ndarray
) -> np.ndarray:
    pts = points.astype(np.float32)

    sums = pts.sum(axis=1)

    diffs = np.diff(
        pts,
        axis=1
    ).reshape(-1)

    return np.array(
        [
            pts[np.argmin(sums)],
            pts[np.argmin(diffs)],
            pts[np.argmax(sums)],
            pts[np.argmax(diffs)],
        ],
        dtype=np.float32
    )


def intersection_area(
    a,
    b
) -> float:
    ax1, ay1, ax2, ay2 = a
    bx1, by1, bx2, by2 = b

    x1 = max(ax1, bx1)
    y1 = max(ay1, by1)
    x2 = min(ax2, bx2)
    y2 = min(ay2, by2)

    return (
        max(0.0, x2 - x1)
        * max(0.0, y2 - y1)
    )


def score_candidate(
    points: np.ndarray,
    target_rect,
) -> float | None:
    tx1, ty1, tx2, ty2 = target_rect

    target_width = tx2 - tx1
    target_height = ty2 - ty1

    target_area = (
        target_width
        * target_height
    )

    if target_area <= 0:
        return None

    target_centre = (
        (tx1 + tx2) / 2,
        (ty1 + ty2) / 2,
    )

    target_diagonal = math.hypot(
        target_width,
        target_height
    )

    contour = points.reshape(
        -1,
        1,
        2
    ).astype(np.float32)

    area = abs(
        cv2.contourArea(contour)
    )

    if area <= 0:
        return None

    rect = cv2.minAreaRect(
        contour
    )

    rw, rh = rect[1]

    if rw < 20 or rh < 20:
        return None

    long_side = max(rw, rh)
    short_side = min(rw, rh)

    if short_side <= 0:
        return None

    aspect_ratio = (
        long_side / short_side
    )

    # Loose enough for perspective and sleeves.
    if not (
        1.05
        <= aspect_ratio
        <= 1.90
    ):
        return None

    rectangle_area = rw * rh

    if rectangle_area <= 0:
        return None

    rectangularity = min(
        1.0,
        area / rectangle_area
    )

    if rectangularity < 0.50:
        return None

    cx, cy, cw, ch = cv2.boundingRect(
        points.astype(np.float32)
    )

    candidate_rect = (
        float(cx),
        float(cy),
        float(cx + cw),
        float(cy + ch),
    )

    candidate_centre = (
        cx + (cw / 2),
        cy + (ch / 2),
    )

    centre_distance = math.hypot(
        candidate_centre[0]
            - target_centre[0],
        candidate_centre[1]
            - target_centre[1],
    )

    area_ratio = (
        area / target_area
    )

    # OpenCV may enlarge/refine AI's box,
    # but it cannot select something many
    # times larger or much smaller.
    if not (
        0.28
        <= area_ratio
        <= 3.00
    ):
        return None

    overlap = intersection_area(
        candidate_rect,
        target_rect
    )

    overlap_target = (
        overlap / target_area
    )

    if overlap_target < 0.18:
        return None

    inside = cv2.pointPolygonTest(
        contour,
        target_centre,
        False
    ) >= 0

    # The candidate should contain the AI
    # object's centre, or be very close to it.
    if (
        not inside
        and centre_distance
            > target_diagonal * 0.35
    ):
        return None

    if (
        centre_distance
        > target_diagonal * 0.70
    ):
        return None

    aspect_score = max(
        0.0,
        1.0
        - abs(
            aspect_ratio - 1.40
        ) / 0.60
    )

    size_score = math.exp(
        -abs(
            math.log(
                max(
                    area_ratio,
                    0.001
                )
            )
        )
    )

    centre_score = max(
        0.0,
        1.0
        - (
            centre_distance
            / max(
                target_diagonal * 0.70,
                1
            )
        )
    )

    overlap_score = min(
        1.0,
        overlap_target
    )

    inside_bonus = (
        0.12
        if inside
        else 0.0
    )

    return (
        aspect_score * 0.22
        + rectangularity * 0.18
        + size_score * 0.22
        + centre_score * 0.20
        + overlap_score * 0.18
        + inside_bonus
    )


def detect_card_in_roi(
    roi: np.ndarray,
    target_rect,
):
    height, width = roi.shape[:2]

    gray = cv2.cvtColor(
        roi,
        cv2.COLOR_BGR2GRAY
    )

    clahe = cv2.createCLAHE(
        clipLimit=2.5,
        tileGridSize=(8, 8)
    )

    gray = clahe.apply(gray)

    blurred = cv2.GaussianBlur(
        gray,
        (5, 5),
        0
    )

    candidates = []

    for low, high in [
        (20, 65),
        (30, 90),
        (45, 125),
        (60, 160),
        (80, 200),
    ]:
        edges = cv2.Canny(
            blurred,
            low,
            high
        )

        for kernel_size in [
            3,
            5,
        ]:
            kernel = cv2.getStructuringElement(
                cv2.MORPH_RECT,
                (
                    kernel_size,
                    kernel_size
                )
            )

            closed = cv2.morphologyEx(
                edges,
                cv2.MORPH_CLOSE,
                kernel,
                iterations=2
            )

            contours, _ = cv2.findContours(
                closed,
                cv2.RETR_LIST,
                cv2.CHAIN_APPROX_SIMPLE
            )

            for contour in contours:
                contour_area = abs(
                    cv2.contourArea(
                        contour
                    )
                )

                target_area = (
                    (
                        target_rect[2]
                        - target_rect[0]
                    )
                    *
                    (
                        target_rect[3]
                        - target_rect[1]
                    )
                )

                if contour_area < (
                    target_area * 0.15
                ):
                    continue

                if contour_area > (
                    target_area * 4.0
                ):
                    continue

                perimeter = cv2.arcLength(
                    contour,
                    True
                )

                if perimeter <= 0:
                    continue

                for epsilon in [
                    0.012,
                    0.018,
                    0.025,
                    0.035,
                    0.05,
                ]:
                    approx = cv2.approxPolyDP(
                        contour,
                        epsilon
                            * perimeter,
                        True
                    )

                    if len(approx) != 4:
                        continue

                    if not cv2.isContourConvex(
                        approx
                    ):
                        continue

                    points = order_points(
                        approx.reshape(
                            4,
                            2
                        )
                    )

                    score = score_candidate(
                        points,
                        target_rect
                    )

                    if score is not None:
                        candidates.append(
                            (
                                score,
                                points
                            )
                        )

                # Also consider the minimum
                # rotated rectangle for this contour.
                rect = cv2.minAreaRect(
                    contour
                )

                rw, rh = rect[1]

                if (
                    rw >= 20
                    and rh >= 20
                ):
                    points = order_points(
                        cv2.boxPoints(
                            rect
                        )
                    )

                    score = score_candidate(
                        points,
                        target_rect
                    )

                    if score is not None:
                        candidates.append(
                            (
                                score * 0.94,
                                points
                            )
                        )

    if not candidates:
        return None

    candidates.sort(
        key=lambda entry: entry[0],
        reverse=True
    )

    score, points = candidates[0]

    return {
        "score": float(score),
        "points": points,
    }



# VERTICAL_LAYOUT_CALIBRATION_V1
@app.post("/layout/vertical")
def vertical_layout(
    request: RoiDebugRequest
):
    source_path = resolve_storage_path(
        request.path
    )

    image = cv2.imread(
        str(source_path)
    )

    if image is None:
        raise HTTPException(
            status_code=422,
            detail="Could not decode image"
        )

    image_height, image_width = (
        image.shape[:2]
    )

    vertical_items = []

    for item in request.items:
        box = item.bounding_box

        width = max(
            0.001,
            float(box.width)
        )

        height = max(
            0.001,
            float(box.height)
        )

        if (
            height >= width * 2.2
            and height >= 0.15
        ):
            vertical_items.append(
                item
            )

    #
    # Only calibrate actual rows/groups.
    # A single tall object does not need this.
    #
    if len(vertical_items) < 4:
        return {
            "detected": False,
            "reason":
                "not_enough_vertical_items",
            "vertical_items":
                len(vertical_items),
        }

    y_min = min(
        float(
            item.bounding_box.y
        )
        for item in vertical_items
    )

    y_max = max(
        float(
            item.bounding_box.y
            + item.bounding_box.height
        )
        for item in vertical_items
    )

    #
    # Expand slightly beyond the AI vertical
    # region so physical edges are captured.
    #
    y1_ratio = max(
        0.0,
        y_min - 0.05
    )

    y2_ratio = min(
        1.0,
        y_max + 0.03
    )

    y1 = int(
        image_height
        * y1_ratio
    )

    y2 = int(
        image_height
        * y2_ratio
    )

    if y2 - y1 < 100:
        return {
            "detected": False,
            "reason":
                "vertical_roi_too_small",
        }

    roi = image[
        y1:y2,
        :
    ]

    gray = cv2.cvtColor(
        roi,
        cv2.COLOR_BGR2GRAY
    )

    clahe = cv2.createCLAHE(
        clipLimit=2.0,
        tileGridSize=(8, 8)
    )

    gray = clahe.apply(
        gray
    )

    #
    # X gradient measures vertical edges.
    #
    gx = cv2.Sobel(
        gray,
        cv2.CV_32F,
        1,
        0,
        ksize=3
    )

    strength = np.abs(
        gx
    )

    threshold = np.percentile(
        strength,
        70
    )

    strength[
        strength < threshold
    ] = 0

    profile = strength.mean(
        axis=0
    )

    window = max(
        21,
        int(
            image_width
            * 0.012
        )
    )

    if window % 2 == 0:
        window += 1

    kernel = (
        np.ones(
            window,
            dtype=np.float32
        )
        / window
    )

    smooth = np.convolve(
        profile,
        kernel,
        mode="same"
    )

    minimum_distance = max(
        45,
        int(
            image_width
            * 0.018
        )
    )

    candidates = []

    for x in range(
        minimum_distance,
        image_width
        - minimum_distance
    ):
        left = max(
            0,
            x - minimum_distance
        )

        right = min(
            image_width,
            x
            + minimum_distance
            + 1
        )

        if smooth[x] == np.max(
            smooth[left:right]
        ):
            candidates.append(
                x
            )

    candidates = sorted(
        candidates,
        key=lambda value:
            smooth[value],
        reverse=True
    )

    selected = []

    for x in candidates:
        if all(
            abs(
                x - existing
            ) >= minimum_distance
            for existing in selected
        ):
            selected.append(
                x
            )

        if len(selected) >= 24:
            break

    selected.sort()

    selected = [
        x
        for x in selected
        if int(
            image_width * 0.05
        )
        < x
        < int(
            image_width * 0.95
        )
    ]

    if len(selected) < 4:
        return {
            "detected": False,
            "reason":
                "insufficient_edge_peaks",
            "edge_peaks":
                len(selected),
        }

    group_left = (
        selected[0]
        / image_width
    )

    group_right = (
        selected[-1]
        / image_width
    )

    span = (
        group_right
        - group_left
    )

    if (
        span < 0.25
        or span > 0.95
    ):
        return {
            "detected": False,
            "reason":
                "implausible_group_span",
            "group_left":
                group_left,
            "group_right":
                group_right,
        }

    expected = len(
        vertical_items
    )

    peak_ratio = min(
        1.0,
        len(selected)
        / max(
            4,
            expected
        )
    )

    span_score = min(
        1.0,
        span / 0.55
    )

    confidence = min(
        0.99,
        0.50
        + (
            peak_ratio
            * 0.30
        )
        + (
            span_score
            * 0.19
        )
    )

    return {
        "detected": True,
        "orientation":
            "vertical",
        "group_left":
            round(
                group_left,
                6
            ),
        "group_right":
            round(
                group_right,
                6
            ),
        "confidence":
            round(
                confidence,
                4
            ),
        "edge_peaks": [
            round(
                x / image_width,
                6
            )
            for x in selected
        ],
        "vertical_items":
            expected,
        "roi": {
            "top":
                round(
                    y1_ratio,
                    6
                ),
            "bottom":
                round(
                    y2_ratio,
                    6
                ),
        },
    }


@app.get("/health")
def health():
    return {
        "status": "ok",
        "service": "sellmyjunk-vision",
        "version": "1.4",
        "opencv": cv2.__version__,
        "numpy": np.__version__,
        "storage_available":
            DATA_ROOT.exists(),
    }


@app.post("/debug/card-rois")
def debug_card_rois(
    request: RoiDebugRequest
):
    source_path = resolve_storage_path(
        request.path
    )

    image = cv2.imread(
        str(source_path)
    )

    if image is None:
        raise HTTPException(
            status_code=422,
            detail=(
                "OpenCV could not decode image"
            )
        )

    image_height, image_width = (
        image.shape[:2]
    )

    diagnostic = image.copy()

    results = []

    for item in request.items:
        box = item.bounding_box

        object_x1 = (
            box.x * image_width
        )

        object_y1 = (
            box.y * image_height
        )

        object_x2 = (
            (box.x + box.width)
            * image_width
        )

        object_y2 = (
            (box.y + box.height)
            * image_height
        )

        object_width = (
            object_x2 - object_x1
        )

        object_height = (
            object_y2 - object_y1
        )

        # Much tighter search than v1.2.
        pad_x = max(
            object_width * 0.45,
            image_width * 0.01
        )

        pad_y = max(
            object_height * 0.45,
            image_height * 0.01
        )

        search_x1 = max(
            0,
            int(
                object_x1 - pad_x
            )
        )

        search_y1 = max(
            0,
            int(
                object_y1 - pad_y
            )
        )

        search_x2 = min(
            image_width,
            int(
                object_x2 + pad_x
            )
        )

        search_y2 = min(
            image_height,
            int(
                object_y2 + pad_y
            )
        )

        if (
            search_x2 <= search_x1
            or search_y2 <= search_y1
        ):
            continue

        roi = image[
            search_y1:search_y2,
            search_x1:search_x2
        ]

        # AI bbox expressed inside ROI.
        target_rect = (
            object_x1 - search_x1,
            object_y1 - search_y1,
            object_x2 - search_x1,
            object_y2 - search_y1,
        )

        detected = detect_card_in_roi(
            roi,
            target_rect
        )

        # BLUE = OpenCV search region.
        cv2.rectangle(
            diagnostic,
            (
                search_x1,
                search_y1
            ),
            (
                search_x2,
                search_y2
            ),
            (255, 0, 0),
            3
        )

        # YELLOW = original AI box.
        cv2.rectangle(
            diagnostic,
            (
                int(object_x1),
                int(object_y1)
            ),
            (
                int(object_x2),
                int(object_y2)
            ),
            (0, 255, 255),
            5
        )

        result = {
            "item_id": item.id,
            "title": item.title,
            "found": False,
        }

        if detected is not None:
            global_points = (
                detected["points"]
                + np.array(
                    [
                        search_x1,
                        search_y1
                    ],
                    dtype=np.float32
                )
            )

            points_int = (
                global_points
                .astype(np.int32)
            )

            # GREEN = OpenCV's refined object.
            cv2.polylines(
                diagnostic,
                [points_int],
                True,
                (0, 255, 0),
                7,
                cv2.LINE_AA
            )

            centre = points_int.mean(
                axis=0
            ).astype(int)

            cv2.circle(
                diagnostic,
                tuple(centre),
                30,
                (0, 0, 255),
                -1
            )

            cv2.putText(
                diagnostic,
                str(item.id),
                (
                    int(centre[0]) - 25,
                    int(centre[1]) + 9
                ),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.70,
                (255, 255, 255),
                2,
                cv2.LINE_AA
            )

            corners = []

            for point in global_points:
                corners.append({
                    "x": round(
                        float(
                            point[0]
                            / image_width
                        ),
                        5
                    ),
                    "y": round(
                        float(
                            point[1]
                            / image_height
                        ),
                        5
                    ),
                })

            result.update({
                "found": True,
                "score": round(
                    detected["score"],
                    3
                ),
                "corners": corners,
            })
        else:
            centre_x = int(
                (
                    object_x1
                    + object_x2
                ) / 2
            )

            centre_y = int(
                (
                    object_y1
                    + object_y2
                ) / 2
            )

            cv2.putText(
                diagnostic,
                f"{item.id} NO MATCH",
                (
                    centre_x - 70,
                    centre_y
                ),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.55,
                (0, 0, 255),
                2,
                cv2.LINE_AA
            )

        results.append(result)

    debug_directory = (
        source_path.parent.parent
        / "debug"
    )

    debug_directory.mkdir(
        parents=True,
        exist_ok=True
    )

    output_name = (
        source_path.stem
        + "-roi-card-detection-v13.jpg"
    )

    output_path = (
        debug_directory
        / output_name
    )

    cv2.imwrite(
        str(output_path),
        diagnostic,
        [
            cv2.IMWRITE_JPEG_QUALITY,
            88
        ]
    )

    relative_debug_path = (
        output_path.relative_to(
            DATA_ROOT / "public"
        )
    )

    return {
        "status": "ok",
        "version": "1.3",
        "source": request.path,
        "item_count":
            len(request.items),
        "found_count": sum(
            1
            for result in results
            if result["found"]
        ),
        "debug_path":
            str(relative_debug_path),
        "items": results,
    }
