# main.py - FastAPI Microservice for NrityaHub AI Movement Analysis
import os
import time
import logging
from pathlib import Path
from typing import Optional, Dict, Any
from fastapi import FastAPI, BackgroundTasks, HTTPException
from pydantic import BaseModel

import config
import database
from pose_detector import PoseFeatureExtractor
from dtw_aligner import DTWMovementScorer
from visualizer import KeyframeVisualizer

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger("nrityahub_ai")

app = FastAPI(
    title="NrityaHub AI Movement Analysis Service",
    description="Automated Bharatanatyam posture and temporal alignment evaluation engine",
    version="1.0.0"
)

# Global model & engine instances
pose_extractor: Optional[PoseFeatureExtractor] = None
dtw_scorer = DTWMovementScorer()
visualizer = KeyframeVisualizer()

@app.on_event("startup")
def startup_event():
    global pose_extractor
    logger.info("Initializing database schema...")
    database.ensure_tables()
    logger.info("Loading MediaPipe Pose Landmarker...")
    pose_extractor = PoseFeatureExtractor()
    logger.info("NrityaHub AI Service initialized and ready.")

class AnalyzeRequest(BaseModel):
    submission_id: int
    reference_id: int
    student_video_path: str
    reference_video_path: str
    category: Optional[str] = "Tatta Adavu"
    callback_url: Optional[str] = None

def process_video_analysis(request: AnalyzeRequest):
    """
    Background worker task: extracts landmarks, aligns sequences with FastDTW,
    computes scores, renders visual snapshot, and updates MySQL.
    """
    sub_id = request.submission_id
    start_time = time.time()
    logger.info(f"Starting AI movement analysis for submission #{sub_id}...")

    try:
        database.update_status(sub_id, "PROCESSING")

        # Resolve paths
        s_path = Path(request.student_video_path)
        r_path = Path(request.reference_video_path)

        # Allow relative paths based on backend dir
        if not s_path.is_absolute():
            s_path = config.BACKEND_DIR / s_path
        if not r_path.is_absolute():
            r_path = config.BACKEND_DIR / r_path

        if not s_path.exists():
            raise FileNotFoundError(f"Student video not found: {s_path}")
        if not r_path.exists():
            raise FileNotFoundError(f"Reference video not found: {r_path}")

        # 1. Pose feature extraction
        logger.info(f"[#{sub_id}] Extracting student video landmarks...")
        student_data = pose_extractor.extract_video_features(str(s_path))

        logger.info(f"[#{sub_id}] Extracting reference video landmarks...")
        reference_data = pose_extractor.extract_video_features(str(r_path))

        # 2. FastDTW temporal alignment and scoring
        logger.info(f"[#{sub_id}] Running FastDTW movement alignment...")
        eval_result = dtw_scorer.evaluate_movement(student_data, reference_data)

        # 3. Generate side-by-side keyframe snapshot
        logger.info(f"[#{sub_id}] Generating comparison snapshot...")
        snapshot_url = visualizer.generate_comparison_snapshot(sub_id, student_data, reference_data)

        # 4. Save results to database
        duration = time.time() - start_time
        database.save_completed_analysis(sub_id, eval_result, snapshot_url, duration)

        logger.info(f"[#{sub_id}] AI analysis completed successfully in {duration:.2f}s with score {eval_result['overall_score']}%.")

    except Exception as e:
        logger.error(f"[#{sub_id}] AI analysis failed: {str(e)}", exc_info=True)
        database.update_status(sub_id, "FAILED", error_message=str(e))

@app.get("/health")
def health():
    return {
        "status": "healthy",
        "service": "NrityaHub AI Movement Analysis",
        "version": "1.0.0",
        "database": "connected"
    }

@app.post("/analyze")
def analyze(req: AnalyzeRequest, background_tasks: BackgroundTasks):
    """
    Submits a new video analysis request for processing in the background.
    """
    job_id = database.create_or_get_job(req.submission_id, req.reference_id)
    background_tasks.add_task(process_video_analysis, req)
    return {
        "success": True,
        "message": f"AI analysis job queued for submission #{req.submission_id}.",
        "job_id": job_id,
        "status": "QUEUED"
    }

@app.get("/analysis/{submission_id}")
def get_analysis(submission_id: int):
    """
    Retrieves the status and analysis scores for a submission.
    """
    row = database.get_analysis_by_submission(submission_id)
    if not row:
        raise HTTPException(status_code=404, detail="No AI analysis found for this submission.")
    return {
        "success": True,
        "analysis": row
    }

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host=config.SERVICE_HOST, port=config.SERVICE_PORT)
