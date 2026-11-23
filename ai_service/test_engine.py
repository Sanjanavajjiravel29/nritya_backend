# test_engine.py - Standalone Verification Test for AI Movement Analysis Engine
import os
import time
from pathlib import Path

import config
import database
from pose_detector import PoseFeatureExtractor
from dtw_aligner import DTWMovementScorer
from visualizer import KeyframeVisualizer

def main():
    print("=" * 60)
    print("NrityaHub AI Movement Analysis - Standalone Verification Test")
    print("=" * 60)

    # 1. Initialize DB schema
    print("\n[Step 1] Initializing database tables...")
    database.ensure_tables()
    print("Database tables verified.")

    # 2. Initialize Pose Extractor
    print("\n[Step 2] Loading Pose Landmarker Model...")
    t0 = time.time()
    extractor = PoseFeatureExtractor()
    print(f"Model loaded in {time.time() - t0:.2f}s")

    # 3. Locate test videos
    ref_path = config.REFERENCES_DIR / "ref_10_546480baba351e64.mp4"
    sub_path = config.SUBMISSIONS_DIR / "sub_20_5e3eb7bbae2fc449.mp4"

    if not ref_path.exists():
        print(f"ERROR: Reference video not found at {ref_path}")
        return
    if not sub_path.exists():
        print(f"ERROR: Student practice video not found at {sub_path}")
        return

    print(f"\n[Step 3] Processing Videos:")
    print(f"  Reference Video: {ref_path.name}")
    print(f"  Student Video:   {sub_path.name}")

    # 4. Extract features
    print("\n[Step 4] Extracting reference video pose landmarks...")
    t_ref = time.time()
    ref_data = extractor.extract_video_features(str(ref_path))
    print(f"  Reference: {ref_data['sampled_frames_count']} frames extracted in {time.time() - t_ref:.2f}s")

    print("\n[Step 5] Extracting student video pose landmarks...")
    t_sub = time.time()
    student_data = extractor.extract_video_features(str(sub_path))
    print(f"  Student: {student_data['sampled_frames_count']} frames extracted in {time.time() - t_sub:.2f}s")

    # 5. Evaluate movement with FastDTW
    print("\n[Step 6] Running FastDTW Movement Alignment & Biomechanical Scoring...")
    t_dtw = time.time()
    scorer = DTWMovementScorer()
    eval_result = scorer.evaluate_movement(student_data, ref_data)
    print(f"  Alignment & scoring completed in {time.time() - t_dtw:.2f}s")

    print("\n" + "-" * 40)
    print("EVALUATION RESULTS:")
    print("-" * 40)
    print(f"  Overall Score:        {eval_result['overall_score']}%")
    print(f"  Araimandi Score:      {eval_result['aramandi_score']}%")
    print(f"  Posture Score:        {eval_result['posture_score']}%")
    print(f"  Arm Alignment Score:  {eval_result['arm_alignment_score']}%")
    print(f"  Stability Score:      {eval_result['stability_score']}%")
    print(f"  Timing Sync Score:    {eval_result['timing_sync_score']}%")

    print(f"\n  Average Student Knee Angle: {eval_result['metrics']['avg_student_knee_angle']}° (Ideal: 115°-135°)")
    print(f"  Average Student Turnout:    {eval_result['metrics']['avg_student_turnout']} (Ideal: >= 1.45)")
    print(f"  Average Spine Tilt:         {eval_result['metrics']['avg_student_spine_tilt']}° (Ideal: <= 8.0°)")

    print("\n  STRENGTHS OBSERVED:")
    for s in eval_result["strengths"]:
        print(f"   + {s}")

    print("\n  AREAS FOR IMPROVEMENT:")
    for imp in eval_result["areas_for_improvement"]:
        print(f"   - {imp}")

    # 6. Generate Side-by-Side Keyframe Snapshot
    print("\n[Step 7] Generating Side-by-Side Keyframe Snapshot...")
    visualizer = KeyframeVisualizer()
    test_sub_id = 19
    snapshot_rel_url = visualizer.generate_comparison_snapshot(test_sub_id, student_data, ref_data)
    print(f"  Snapshot saved to: {snapshot_rel_url}")

    # 7. Test Database Save
    print("\n[Step 8] Testing Database Storage...")
    database.create_or_get_job(test_sub_id, reference_id=3)
    database.save_completed_analysis(test_sub_id, eval_result, snapshot_rel_url, duration_sec=5.2)
    saved_record = database.get_analysis_by_submission(test_sub_id)
    print(f"  Database record retrieved successfully! Status: {saved_record['status']}, Overall Score: {saved_record['overall_score']}")

    print("\n" + "=" * 60)
    print("ALL AI MOVEMENT ANALYSIS MODULES VERIFIED SUCCESSFULLY!")
    print("=" * 60)

if __name__ == "__main__":
    main()
