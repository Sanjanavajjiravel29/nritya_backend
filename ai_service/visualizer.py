# visualizer.py - Side-by-Side Pose Overlay Keyframe Generator
import cv2
import numpy as np
from pathlib import Path
from typing import List, Tuple, Any, Optional
import config

POSE_CONNECTIONS = [
    # Torso
    (11, 12), (11, 23), (12, 24), (23, 24),
    # Arms
    (11, 13), (13, 15), (12, 14), (14, 16),
    # Legs
    (23, 25), (25, 27), (27, 29), (27, 31),
    (24, 26), (26, 28), (28, 30), (28, 32)
]

class KeyframeVisualizer:
    """
    Renders side-by-side pose overlay visual comparisons between student and reference keyframes.
    """

    @staticmethod
    def draw_skeleton(img: np.ndarray, landmarks: List[Any], color=(0, 255, 0), thickness=2) -> np.ndarray:
        """
        Overlays 2D skeletal lines and joint circles on an image frame.
        """
        h, w = img.shape[:2]

        pts = {}
        for idx in range(min(33, len(landmarks))):
            lm = landmarks[idx]
            # lm can be a tuple (x, y, z) or MediaPipe landmark object
            lx = lm[0] if isinstance(lm, (tuple, list)) else lm.x
            ly = lm[1] if isinstance(lm, (tuple, list)) else lm.y
            px, py = int(lx * w), int(ly * h)
            pts[idx] = (px, py)
            cv2.circle(img, (px, py), 4, (0, 0, 255), -1)

        for p1_idx, p2_idx in POSE_CONNECTIONS:
            if p1_idx in pts and p2_idx in pts:
                cv2.line(img, pts[p1_idx], pts[p2_idx], color, thickness)

        return img

    def generate_comparison_snapshot(
        self,
        submission_id: int,
        student_data: dict,
        reference_data: dict,
        output_dir: Optional[Path] = None
    ) -> str:
        """
        Creates a side-by-side composite comparison image and saves it to uploads/ai_reports/.
        Returns relative URL path.
        """
        if output_dir is None:
            output_dir = config.AI_REPORTS_DIR
        output_dir.mkdir(parents=True, exist_ok=True)

        # 1. Select representative keyframes (prefer frame with lowest avg_knee_angle = deepest Araimandi)
        s_frames = student_data.get("raw_key_frames", [])
        r_frames = reference_data.get("raw_key_frames", [])

        if not s_frames or not r_frames:
            # Fallback blank frame if video has zero pose frames
            h, w = 480, 640
            student_img = np.zeros((h, w, 3), dtype=np.uint8)
            ref_img = np.zeros((h, w, 3), dtype=np.uint8)
        else:
            _, student_rgb, s_lm = s_frames[len(s_frames) // 2]
            _, ref_rgb, r_lm = r_frames[len(r_frames) // 2]

            student_img = cv2.cvtColor(student_rgb, cv2.COLOR_RGB2BGR)
            ref_img = cv2.cvtColor(ref_rgb, cv2.COLOR_RGB2BGR)

            student_img = self.draw_skeleton(student_img, s_lm, color=(50, 205, 50), thickness=3)
            ref_img = self.draw_skeleton(ref_img, r_lm, color=(255, 140, 0), thickness=3)

        # 2. Resize to matching height (e.g. 540p)
        target_h = 540
        def resize_to_h(im):
            h, w = im.shape[:2]
            target_w = int(w * (target_h / max(1, h)))
            return cv2.resize(im, (target_w, target_h), interpolation=cv2.INTER_AREA)

        s_resized = resize_to_h(student_img)
        r_resized = resize_to_h(ref_img)

        # 3. Concatenate horizontally side-by-side
        composite = np.hstack([s_resized, r_resized])
        ch, cw = composite.shape[:2]

        # 4. Draw header banner
        header = np.zeros((60, cw, 3), dtype=np.uint8)
        header[:] = (45, 12, 60) # Deep Plum banner
        cv2.putText(header, "STUDENT PRACTICE (AI TRACKED)", (30, 40), cv2.FONT_HERSHEY_SIMPLEX, 0.75, (255, 255, 255), 2)
        cv2.putText(header, "INSTRUCTOR REFERENCE", (cw // 2 + 30, 40), cv2.FONT_HERSHEY_SIMPLEX, 0.75, (255, 255, 255), 2)

        final_image = np.vstack([header, composite])

        # 5. Save output JPEG
        filename = f"comp_sub_{submission_id}.jpg"
        save_path = output_dir / filename
        cv2.imwrite(str(save_path), final_image, [cv2.IMWRITE_JPEG_QUALITY, 85])

        return f"uploads/ai_reports/{filename}"
