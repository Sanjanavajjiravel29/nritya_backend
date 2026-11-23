# dtw_aligner.py - FastDTW Temporal Synchronization & Bharatanatyam Scoring Algorithm
import numpy as np
from scipy.spatial.distance import euclidean
from fastdtw import fastdtw
from typing import Dict, Any, List, Tuple
import config

class DTWMovementScorer:
    """
    Performs temporal synchronization between student practice video and instructor
    reference demonstration, computing objective biomechanical fidelity scores.
    """

    @staticmethod
    def _build_feature_vector(frame_feat: Dict[str, Any]) -> np.ndarray:
        """
        Constructs normalized feature vector for FastDTW temporal comparison:
        [avg_knee_angle / 180, turnout_ratio / 2.0, spine_tilt / 45, left_elbow_droop, right_elbow_droop]
        """
        return np.array([
            frame_feat["avg_knee_angle"] / 180.0,
            min(2.0, frame_feat["turnout_ratio"]) / 2.0,
            min(45.0, frame_feat["spine_tilt"]) / 45.0,
            np.clip(frame_feat["left_elbow_droop"], -0.5, 0.5),
            np.clip(frame_feat["right_elbow_droop"], -0.5, 0.5),
            frame_feat["left_knee_angle"] / 180.0,
            frame_feat["right_knee_angle"] / 180.0
        ], dtype=np.float32)

    def evaluate_movement(self, student_data: Dict[str, Any], reference_data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Aligns student and reference sequences and computes scores and actionable feedback.
        """
        student_frames = student_data.get("features", [])
        ref_frames = reference_data.get("features", [])

        if not student_frames or not ref_frames:
            raise ValueError("Insufficient pose detection frames extracted from video(s).")

        # 1. Build sequence matrices for FastDTW
        seq_student = np.array([self._build_feature_vector(f) for f in student_frames])
        seq_ref = np.array([self._build_feature_vector(f) for f in ref_frames])

        # 2. Run FastDTW alignment
        dtw_distance, warping_path = fastdtw(seq_student, seq_ref, dist=euclidean)

        # 3. Analyze aligned pairs
        knee_diffs = []
        turnout_diffs = []
        spine_diffs = []
        elbow_diffs = []

        student_knee_angles = []
        student_turnouts = []
        student_spines = []
        student_elbow_droops = []

        aligned_time_series = []

        for s_idx, r_idx in warping_path:
            sf = student_frames[s_idx]
            rf = ref_frames[r_idx]

            kd = abs(sf["avg_knee_angle"] - rf["avg_knee_angle"])
            td = abs(sf["turnout_ratio"] - rf["turnout_ratio"])
            sd = abs(sf["spine_tilt"] - rf["spine_tilt"])
            ed = (abs(sf["left_elbow_droop"] - rf["left_elbow_droop"]) + abs(sf["right_elbow_droop"] - rf["right_elbow_droop"])) / 2.0

            knee_diffs.append(kd)
            turnout_diffs.append(td)
            spine_diffs.append(sd)
            elbow_diffs.append(ed)

            student_knee_angles.append(sf["avg_knee_angle"])
            student_turnouts.append(sf["turnout_ratio"])
            student_spines.append(sf["spine_tilt"])
            student_elbow_droops.append((sf["left_elbow_droop"] + sf["right_elbow_droop"]) / 2.0)

            if len(aligned_time_series) < 60: # sample subset for charting
                aligned_time_series.append({
                    "time_sec": sf["time_sec"],
                    "student_knee": sf["avg_knee_angle"],
                    "ref_knee": rf["avg_knee_angle"],
                    "student_spine": sf["spine_tilt"],
                    "ref_spine": rf["spine_tilt"]
                })

        # 4. Compute Sub-Component Scores (0 to 100)
        # Araimandi Score: penalizes deviation from reference knee angle and shallow stance
        mean_knee_diff = float(np.mean(knee_diffs))
        aramandi_score = max(0.0, min(100.0, 100.0 - (mean_knee_diff * 1.5)))

        # Posture Score: penalizes spine tilt and shoulder deviation
        mean_spine_diff = float(np.mean(spine_diffs))
        posture_score = max(0.0, min(100.0, 100.0 - (mean_spine_diff * 4.0)))

        # Arm Alignment Score: penalizes elbow droop and curvature mismatch
        mean_elbow_diff = float(np.mean(elbow_diffs))
        arm_alignment_score = max(0.0, min(100.0, 100.0 - (mean_elbow_diff * 200.0)))

        # Stability Score: penalizes high variance in torso tilt
        spine_variance = float(np.var(student_spines))
        stability_score = max(0.0, min(100.0, 100.0 - (spine_variance * 2.5)))

        # Timing Synchronization Score: path linearity relative to ideal diagonal
        ideal_ratio = len(ref_frames) / max(1, len(student_frames))
        path_deviations = [abs(r_idx - s_idx * ideal_ratio) for s_idx, r_idx in warping_path]
        mean_path_dev = float(np.mean(path_deviations)) / max(1, len(ref_frames))
        timing_sync_score = max(0.0, min(100.0, 100.0 - (mean_path_dev * 150.0)))

        # 5. Overall Weighted Composite Score
        overall_score = (
            config.WEIGHT_ARAMANDI * aramandi_score +
            config.WEIGHT_POSTURE * posture_score +
            config.WEIGHT_ARM_ALIGNMENT * arm_alignment_score +
            config.WEIGHT_STABILITY * stability_score +
            config.WEIGHT_TIMING_SYNC * timing_sync_score
        )

        # 6. Generate Rule-Based Bharatanatyam Feedback Insights
        strengths = []
        improvements = []

        avg_student_knee = float(np.mean(student_knee_angles))
        avg_student_turnout = float(np.mean(student_turnouts))
        avg_student_spine = float(np.mean(student_spines))
        avg_student_elbow_droop = float(np.mean(student_elbow_droops))

        # Check posture
        if avg_student_spine <= config.SPINE_VERTICALITY_MAX_TILT:
            strengths.append("Excellent spinal verticality with steady torso alignment.")
        else:
            improvements.append(f"Torso leaned forward by {avg_student_spine:.1f}° on average. Keep spine straight without arching.")

        # Check Araimandi
        if config.ARAIMANDI_IDEAL_MIN_ANGLE <= avg_student_knee <= config.ARAIMANDI_IDEAL_MAX_ANGLE:
            strengths.append("Maintained commendable Araimandi depth in good accordance with instructor demonstration.")
        elif avg_student_knee > config.ARAIMANDI_SHALLOW_THRESHOLD:
            improvements.append(f"Araimandi knee stance was too shallow (measured {avg_student_knee:.1f}° vs ideal 125°). Practice sinking deeper into half-sitting posture.")

        # Check Turnout
        if avg_student_turnout >= config.TURNOUT_RATIO_IDEAL:
            strengths.append("Knee abduction turnout is wide and well-formed (diamond posture).")
        elif avg_student_turnout < config.TURNOUT_RATIO_MIN:
            improvements.append("Knees collapsed inward during foot strikes. Focus on pressing knees sideways for proper turnout.")

        # Check Arm Line
        if avg_student_elbow_droop <= config.ELBOW_DROOP_MAX_FRACTION:
            strengths.append("Natyarambham arm line held steady at shoulder height.")
        else:
            improvements.append("Elbows dropped below shoulder line during transitions. Keep elbows lifted and rounded.")

        # Fallback if student did exceptionally well
        if not improvements:
            improvements.append("Maintain current posture consistency into third-speed execution.")
        if not strengths:
            strengths.append("Clear engagement with step sequence and rhythm structure.")

        return {
            "overall_score": round(overall_score, 1),
            "aramandi_score": round(aramandi_score, 1),
            "posture_score": round(posture_score, 1),
            "arm_alignment_score": round(arm_alignment_score, 1),
            "stability_score": round(stability_score, 1),
            "timing_sync_score": round(timing_sync_score, 1),
            "metrics": {
                "avg_student_knee_angle": round(avg_student_knee, 1),
                "avg_student_turnout": round(avg_student_turnout, 2),
                "avg_student_spine_tilt": round(avg_student_spine, 1),
                "dtw_normalized_distance": round(float(dtw_distance / len(warping_path)), 3),
                "time_series_sample": aligned_time_series
            },
            "strengths": strengths,
            "areas_for_improvement": improvements
        }
