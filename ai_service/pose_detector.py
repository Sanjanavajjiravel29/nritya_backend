# pose_detector.py - MediaPipe Pose Feature Extractor for Bharatanatyam Movement Analysis
import cv2
import math
import numpy as np
from pathlib import Path
from typing import List, Dict, Any, Optional, Tuple

import mediapipe as mp
from mediapipe.tasks import python
from mediapipe.tasks.python import vision

import config

class PoseFeatureExtractor:
    """
    Extracts biomechanical dance landmarks and computes Bharatanatyam posture metrics
    using Google MediaPipe PoseLandmarker.
    """

    def __init__(self, model_path: Optional[str] = None):
        if model_path is None:
            model_path = str(config.BASE_DIR / "models" / "pose_landmarker_full.task")

        base_options = python.BaseOptions(model_asset_path=model_path)
        options = vision.PoseLandmarkerOptions(
            base_options=base_options,
            running_mode=vision.RunningMode.IMAGE,
            output_segmentation_masks=False
        )
        self.detector = vision.PoseLandmarker.create_from_options(options)

    @staticmethod
    def calculate_angle_3d(a: Tuple[float, float, float], b: Tuple[float, float, float], c: Tuple[float, float, float]) -> float:
        """
        Calculates angle at joint B given 3D coordinates A, B, C.
        Returns angle in degrees (0 to 180).
        """
        ba = np.array([a[0] - b[0], a[1] - b[1], a[2] - b[2]])
        bc = np.array([c[0] - b[0], c[1] - b[1], c[2] - b[2]])

        norm_ba = np.linalg.norm(ba)
        norm_bc = np.linalg.norm(bc)
        if norm_ba == 0 or norm_bc == 0:
            return 180.0

        cosine_angle = np.dot(ba, bc) / (norm_ba * norm_bc)
        cosine_angle = np.clip(cosine_angle, -1.0, 1.0)
        return float(np.degrees(np.arccos(cosine_angle)))

    @staticmethod
    def calculate_spine_tilt(mid_shoulder: Tuple[float, float, float], mid_hip: Tuple[float, float, float]) -> float:
        """
        Calculates angle of spine vector (mid_shoulder - mid_hip) relative to true vertical (0, -1, 0).
        Returns deviation in degrees (0 = perfect vertical).
        """
        spine = np.array([mid_shoulder[0] - mid_hip[0], mid_shoulder[1] - mid_hip[1], mid_shoulder[2] - mid_hip[2]])
        vertical = np.array([0.0, -1.0, 0.0]) # inverted image Y axis (down is positive)

        norm_spine = np.linalg.norm(spine)
        if norm_spine == 0:
            return 0.0

        cos_angle = np.dot(spine, vertical) / norm_spine
        cos_angle = np.clip(cos_angle, -1.0, 1.0)
        return float(np.degrees(np.arccos(cos_angle)))

    def extract_video_features(
        self,
        video_path: str,
        target_fps: float = 10.0,
        max_detected_frames: int = 120
    ) -> Dict[str, Any]:
        """
        Processes video file and returns sampled frame landmarks and posture time-series.
        Limits extraction to `max_detected_frames` for optimal responsiveness.
        """
        cap = cv2.VideoCapture(video_path)
        if not cap.isOpened():
            raise FileNotFoundError(f"Could not open video file: {video_path}")

        source_fps = cap.get(cv2.CAP_PROP_FPS) or 30.0
        total_frames = int(cap.get(cv2.CAP_PROP_FRAME_COUNT))
        duration_sec = total_frames / source_fps if source_fps > 0 else 0

        frame_step = max(1, int(round(source_fps / target_fps)))

        frames_features = []
        raw_key_frames = [] # stores selected RGB frames for side-by-side rendering

        frame_idx = 0
        # If video starts with intro card, scan until dancer appears or up to frame 500
        consecutive_misses = 0

        while cap.isOpened():
            ret, frame = cap.read()
            if not ret or len(frames_features) >= max_detected_frames:
                break

            if frame_idx % frame_step == 0:
                rgb_frame = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
                mp_image = mp.Image(image_format=mp.ImageFormat.SRGB, data=rgb_frame)
                detection_result = self.detector.detect(mp_image)

                if detection_result.pose_landmarks and len(detection_result.pose_landmarks) > 0:
                    landmarks = detection_result.pose_landmarks[0]
                    # Landmark indices:
                    # 11: Left Shoulder, 12: Right Shoulder
                    # 13: Left Elbow, 14: Right Elbow
                    # 15: Left Wrist, 16: Right Wrist
                    # 23: Left Hip, 24: Right Hip
                    # 25: Left Knee, 26: Right Knee
                    # 27: Left Ankle, 28: Right Ankle
                    # 29: Left Heel, 30: Right Heel
                    # 31: Left Foot Index, 32: Right Foot Index

                    def pt(idx):
                        lm = landmarks[idx]
                        return (lm.x, lm.y, lm.z)

                    ls, rs = pt(11), pt(12)
                    le, re = pt(13), pt(14)
                    lw, rw = pt(15), pt(16)
                    lh, rh = pt(23), pt(24)
                    lk, rk = pt(25), pt(26)
                    la, ra = pt(27), pt(28)
                    lhe, rhe = pt(29), pt(30)

                    mid_shoulder = ((ls[0] + rs[0]) / 2, (ls[1] + rs[1]) / 2, (ls[2] + rs[2]) / 2)
                    mid_hip = ((lh[0] + rh[0]) / 2, (lh[1] + rh[1]) / 2, (lh[2] + rh[2]) / 2)

                    torso_length = math.sqrt((mid_shoulder[0] - mid_hip[0])**2 + (mid_shoulder[1] - mid_hip[1])**2)
                    torso_length = max(0.05, torso_length) # prevent div by zero

                    # 1. Knee flexion angles (Araimandi depth)
                    left_knee_angle = self.calculate_angle_3d(lh, lk, la)
                    right_knee_angle = self.calculate_angle_3d(rh, rk, ra)
                    avg_knee_angle = (left_knee_angle + right_knee_angle) / 2.0

                    # 2. Turnout ratio (knee span vs hip span)
                    hip_span = max(0.01, abs(lh[0] - rh[0]))
                    knee_span = abs(lk[0] - rk[0])
                    turnout_ratio = knee_span / hip_span

                    # 3. Spine verticality
                    spine_tilt = self.calculate_spine_tilt(mid_shoulder, mid_hip)

                    # 4. Shoulder levelness
                    shoulder_dx = rs[0] - ls[0]
                    shoulder_dy = rs[1] - ls[1]
                    shoulder_tilt = abs(math.degrees(math.atan2(shoulder_dy, shoulder_dx)))

                    # 5. Elbow elevation / Natyarambham (droop fraction relative to torso)
                    # in image coordinates: Y increases downwards. droop = elbow.y - shoulder.y
                    left_elbow_droop = (le[1] - ls[1]) / torso_length
                    right_elbow_droop = (re[1] - rs[1]) / torso_length

                    left_elbow_angle = self.calculate_angle_3d(ls, le, lw)
                    right_elbow_angle = self.calculate_angle_3d(rs, re, rw)

                    # 6. Heel vertical positions for footwork / strike cadence
                    left_heel_y = lhe[1]
                    right_heel_y = rhe[1]

                    time_sec = frame_idx / source_fps

                    feature_point = {
                        "frame_idx": frame_idx,
                        "time_sec": round(time_sec, 3),
                        "left_knee_angle": round(left_knee_angle, 2),
                        "right_knee_angle": round(right_knee_angle, 2),
                        "avg_knee_angle": round(avg_knee_angle, 2),
                        "turnout_ratio": round(turnout_ratio, 3),
                        "spine_tilt": round(spine_tilt, 2),
                        "shoulder_tilt": round(shoulder_tilt, 2),
                        "left_elbow_droop": round(left_elbow_droop, 3),
                        "right_elbow_droop": round(right_elbow_droop, 3),
                        "left_elbow_angle": round(left_elbow_angle, 2),
                        "right_elbow_angle": round(right_elbow_angle, 2),
                        "left_heel_y": round(left_heel_y, 4),
                        "right_heel_y": round(right_heel_y, 4),
                        "landmarks": [(lm.x, lm.y, lm.visibility or 1.0) for lm in landmarks]
                    }
                    frames_features.append(feature_point)

                    # Keep a sample of raw frames for keyframe snapshot generation
                    if len(raw_key_frames) < 30:
                        raw_key_frames.append((frame_idx, rgb_frame, landmarks))
                    elif avg_knee_angle < 135: # retain deep araimandi moments
                        raw_key_frames[len(raw_key_frames) % 30] = (frame_idx, rgb_frame, landmarks)

            frame_idx += 1

        cap.release()

        return {
            "video_path": video_path,
            "duration_sec": round(duration_sec, 2),
            "sampled_frames_count": len(frames_features),
            "features": frames_features,
            "raw_key_frames": raw_key_frames
        }
