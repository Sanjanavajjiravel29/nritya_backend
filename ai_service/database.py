# database.py - Database operations for NrityaHub AI Movement Analysis
import json
import mysql.connector
from datetime import datetime
from typing import Optional, Dict, Any
import config

def get_db_connection():
    return mysql.connector.connect(**config.DB_CONFIG)

def ensure_tables():
    """
    Ensures that the `ai_movement_analyses` table exists in MySQL.
    """
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS ai_movement_analyses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            submission_id INT NOT NULL UNIQUE,
            reference_id INT NOT NULL,
            status ENUM('QUEUED', 'PROCESSING', 'COMPLETED', 'FAILED') NOT NULL DEFAULT 'QUEUED',
            overall_score DECIMAL(5,2) NULL,
            posture_score DECIMAL(5,2) NULL,
            aramandi_score DECIMAL(5,2) NULL,
            arm_alignment_score DECIMAL(5,2) NULL,
            stability_score DECIMAL(5,2) NULL,
            timing_sync_score DECIMAL(5,2) NULL,
            metrics_json LONGTEXT NULL,
            detected_strengths TEXT NULL,
            areas_for_improvement TEXT NULL,
            comparison_snapshot_url VARCHAR(255) NULL,
            error_message TEXT NULL,
            processing_duration_sec DECIMAL(6,2) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME NULL,
            INDEX idx_ai_sub (submission_id),
            INDEX idx_ai_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    """)

    # Also ensure homework_reviews has ai_analysis_id and is_ai_validated columns if not present
    try:
        cursor.execute("SHOW COLUMNS FROM homework_reviews LIKE 'ai_analysis_id';")
        if not cursor.fetchone():
            cursor.execute("ALTER TABLE homework_reviews ADD COLUMN ai_analysis_id INT NULL AFTER submission_id;")
    except Exception:
        pass

    try:
        cursor.execute("SHOW COLUMNS FROM homework_reviews LIKE 'is_ai_validated';")
        if not cursor.fetchone():
            cursor.execute("ALTER TABLE homework_reviews ADD COLUMN is_ai_validated TINYINT(1) NOT NULL DEFAULT 0 AFTER status;")
    except Exception:
        pass

    conn.commit()
    cursor.close()
    conn.close()

def create_or_get_job(submission_id: int, reference_id: int) -> int:
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute("""
        INSERT INTO ai_movement_analyses (submission_id, reference_id, status)
        VALUES (%s, %s, 'QUEUED')
        ON DUPLICATE KEY UPDATE
            reference_id = VALUES(reference_id),
            status = 'QUEUED',
            error_message = NULL,
            completed_at = NULL;
    """, (submission_id, reference_id))
    job_id = cursor.lastrowid
    conn.commit()
    cursor.close()
    conn.close()
    return job_id

def update_status(submission_id: int, status: str, error_message: Optional[str] = None):
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute("""
        UPDATE ai_movement_analyses
        SET status = %s, error_message = %s
        WHERE submission_id = %s;
    """, (status, error_message, submission_id))
    conn.commit()
    cursor.close()
    conn.close()

def save_completed_analysis(
    submission_id: int,
    eval_result: Dict[str, Any],
    snapshot_url: str,
    duration_sec: float
):
    conn = get_db_connection()
    cursor = conn.cursor()

    metrics_str = json.dumps(eval_result.get("metrics", {}))
    strengths_str = "\n".join(eval_result.get("strengths", []))
    improvements_str = "\n".join(eval_result.get("areas_for_improvement", []))

    cursor.execute("""
        UPDATE ai_movement_analyses
        SET status = 'COMPLETED',
            overall_score = %s,
            posture_score = %s,
            aramandi_score = %s,
            arm_alignment_score = %s,
            stability_score = %s,
            timing_sync_score = %s,
            metrics_json = %s,
            detected_strengths = %s,
            areas_for_improvement = %s,
            comparison_snapshot_url = %s,
            processing_duration_sec = %s,
            completed_at = %s,
            error_message = NULL
        WHERE submission_id = %s;
    """, (
        eval_result["overall_score"],
        eval_result["posture_score"],
        eval_result["aramandi_score"],
        eval_result["arm_alignment_score"],
        eval_result["stability_score"],
        eval_result["timing_sync_score"],
        metrics_str,
        strengths_str,
        improvements_str,
        snapshot_url,
        round(duration_sec, 2),
        datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        submission_id
    ))
    conn.commit()
    cursor.close()
    conn.close()

def get_analysis_by_submission(submission_id: int) -> Optional[Dict[str, Any]]:
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT * FROM ai_movement_analyses WHERE submission_id = %s LIMIT 1;", (submission_id,))
    row = cursor.fetchone()
    cursor.close()
    conn.close()
    return row
