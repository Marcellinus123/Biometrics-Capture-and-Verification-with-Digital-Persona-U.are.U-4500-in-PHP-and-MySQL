import os
import cv2
import time
import base64
import mysql.connector
import numpy as np
from flask import Flask, request, jsonify
from datetime import datetime

# Basic server
app = Flask(__name__)

# Configuration
DB_HOST = os.getenv('DB_HOST', 'localhost')
DB_USER = os.getenv('DB_USER', 'root')
DB_PASSWORD = os.getenv('DB_PASSWORD', '')
DB_NAME = os.getenv('DB_NAME', 'bioattend_db_system')

TEMP_FOLDER = 'temp_fingerprints'
ALLOWED_EXTENSIONS = {'png', 'jpg', 'jpeg', 'bmp'}

# Enhanced matching thresholds
VISUAL_MATCH_THRESHOLD = float(os.getenv('VISUAL_MATCH_THRESHOLD', '0.35'))
TOP2_MARGIN = float(os.getenv('TOP2_MARGIN', '0.02'))
MIN_IMAGE_QUALITY = float(os.getenv('MIN_IMAGE_QUALITY', '0.30'))

def get_db_connection():
    try:
        return mysql.connector.connect(
            host=DB_HOST,
            user=DB_USER,
            password=DB_PASSWORD,
            database=DB_NAME,
        )
    except Exception:
        return None

def allowed_file(filename: str) -> bool:
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS

def ensure_dirs():
    if not os.path.exists(TEMP_FOLDER):
        os.makedirs(TEMP_FOLDER, exist_ok=True)

def clean_for_json(obj):
    """Convert numpy types to Python types for JSON serialization"""
    if isinstance(obj, dict):
        return {key: clean_for_json(value) for key, value in obj.items()}
    elif isinstance(obj, list):
        return [clean_for_json(item) for item in obj]
    elif isinstance(obj, (np.integer, np.floating)):
        return float(obj)
    elif isinstance(obj, np.ndarray):
        return obj.tolist()
    else:
        return obj

def assess_image_quality(gray: np.ndarray) -> float:
    if gray is None:
        return 0.0
    try:
        gray = cv2.GaussianBlur(gray, (3, 3), 0)
        lap_var = cv2.Laplacian(gray, cv2.CV_64F).var()
        sharpness = min(1.0, float(lap_var) / 500.0)
        contrast = float(gray.std() / 128.0)
        contrast = max(0.0, min(1.0, contrast))
        
        quality = round(0.6 * sharpness + 0.4 * contrast, 3)
        return quality
    except Exception as e:
        print(f"DEBUG: Error in quality assessment: {e}")
        return 0.0

def preprocess(gray: np.ndarray) -> np.ndarray:
    # Normalize + CLAHE for stability
    gray = cv2.normalize(gray, None, 0, 255, cv2.NORM_MINMAX)
    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
    gray = clahe.apply(gray)
    return gray

def load_gray_from_value(value: str) -> np.ndarray | None:
    # Value can be file path or base64 string
    img = None
    try:
        if isinstance(value, str) and value.lower().endswith(('.png', '.jpg', '.jpeg', '.bmp')):
            # try relative then absolute
            candidates = [
                value,
                os.path.join(os.getcwd(), value),
                os.path.join(os.path.dirname(__file__), value),
            ]
            for p in candidates:
                if os.path.exists(p):
                    img = cv2.imread(p, cv2.IMREAD_GRAYSCALE)
                    if img is not None:
                        break
        if img is None and isinstance(value, str):
            raw = base64.b64decode(value, validate=False)
            nparr = np.frombuffer(raw, np.uint8)
            img = cv2.imdecode(nparr, cv2.IMREAD_GRAYSCALE)
    except Exception:
        img = None
    return img

def fetch_students():
    """Fetch all enrolled students with their fingerprint data"""
    conn = get_db_connection()
    if not conn:
        print("DEBUG: Database connection failed")
        return []
    
    try:
        cursor = conn.cursor(dictionary=True)
        cursor.execute("""
            SELECT student_id, full_name, fingerprint_data 
            FROM students 
            WHERE fingerprint_data IS NOT NULL AND fingerprint_data != ''
        """)
        students = cursor.fetchall()
        print(f"DEBUG: Found {len(students)} enrolled students")
        
        cursor.close()
        conn.close()
        return students
    except Exception as e:
        print(f"DEBUG: Error fetching students: {e}")
        if conn:
            conn.close()
        return []

def compare_probe_to_all(probe: np.ndarray, students: list) -> list:
    """Compare probe fingerprint to all enrolled students"""
    if probe is None:
        print("DEBUG: Probe image is None")
        return []
    
    print(f"DEBUG: Comparing probe image ({probe.shape}) against {len(students)} students")
    results = []
    
    for student in students:
        try:
            print(f"DEBUG: Processing student {student['student_id']}: {student['full_name']}")
            
            # Load student's fingerprint
            student_fp = load_gray_from_value(student['fingerprint_data'])
            if student_fp is None:
                print(f"DEBUG:   Failed to load fingerprint for student {student['student_id']}")
                continue
            
            print(f"DEBUG:   Student fingerprint loaded: {student_fp.shape}")
            
            # Preprocess images
            probe_proc = preprocess(probe)
            student_proc = preprocess(student_fp)
            
            print(f"DEBUG:   Preprocessed - Probe: {probe_proc.shape}, Student: {student_proc.shape}")
            
            # Simple template matching
            result = cv2.matchTemplate(probe_proc, student_proc, cv2.TM_CCOEFF_NORMED)
            score = float(np.max(result))  # Convert to regular Python float
            print(f"DEBUG:   Template score: {score:.4f}")
            
            results.append({
                'student_id': student['student_id'],
                'full_name': student['full_name'],
                'score': score
            })
            
        except Exception as e:
            print(f"DEBUG:   Error processing student {student['student_id']}: {e}")
            continue
    
    print(f"DEBUG: Total results: {len(results)}")
    
    # Sort by score (highest first)
    results.sort(key=lambda x: x['score'], reverse=True)
    
    if results:
        print(f"DEBUG: Best match: {results[0]['full_name']} (ID: {results[0]['student_id']}) with score {results[0]['score']:.4f}")
    
    return results

@app.route('/verify', methods=['POST'])
def verify_fingerprint():
    """Main verification endpoint"""
    try:
        if 'fingerprint' not in request.files:
            return jsonify({'error': 'No fingerprint file provided'}), 400
        
        file = request.files['fingerprint']
        if file.filename == '':
            return jsonify({'error': 'No file selected'}), 400
        
        if not allowed_file(file.filename):
            return jsonify({'error': 'Invalid file type'}), 400
        
        # Save uploaded file temporarily
        temp_path = os.path.join(TEMP_FOLDER, f"probe_{int(time.time())}.png")
        file.save(temp_path)
        
        # Load and process probe image
        probe = cv2.imread(temp_path, cv2.IMREAD_GRAYSCALE)
        if probe is None:
            os.unlink(temp_path)
            return jsonify({'error': 'Could not load uploaded image'}), 400
        
        # Assess image quality
        quality = assess_image_quality(probe)
        print(f"DEBUG: Probe image quality: {quality:.3f} (threshold: {MIN_IMAGE_QUALITY})")
        
        if quality < MIN_IMAGE_QUALITY:
            print(f"DEBUG: Image rejected due to low quality")
            os.unlink(temp_path)
            return jsonify({'error': f'Image quality too low: {quality:.3f} (min: {MIN_IMAGE_QUALITY})'}), 400
        
        # Clean up temp file
        os.unlink(temp_path)
        
        # Fetch students and compare
        students = fetch_students()
        if not students:
            return jsonify({'match': False, 'message': 'No enrolled fingerprints'}), 200
        
        results = compare_probe_to_all(probe, students)
        if not results:
            return jsonify({'match': False, 'message': 'No comparable entries'}), 200
        
        best = results[0]
        second = results[1] if len(results) > 1 else {'score': 0.0}
        
        # Top-2 margin rule
        margin = round(best['score'] - second['score'], 4)
        is_match = best['score'] >= VISUAL_MATCH_THRESHOLD and margin >= TOP2_MARGIN
        
        print(f"DEBUG: Match decision - Best score: {best['score']:.4f}, Threshold: {VISUAL_MATCH_THRESHOLD}")
        print(f"DEBUG: Margin: {margin:.4f}, Required margin: {TOP2_MARGIN}")
        print(f"DEBUG: Final match result: {is_match}")
        
        resp = {
            'match': is_match,
            'best': best,
            'second_best_score': second['score'],
            'margin': margin,
            'quality_score': quality,
            'threshold': VISUAL_MATCH_THRESHOLD,
            'top2_margin_required': TOP2_MARGIN,
            'total_compared': len(results),
            'method': 'Simple fingerprint verification with template matching',
            'timestamp': datetime.now().isoformat(),
            'top_candidates': results[:3]
        }
        
        # Clean all numpy types for JSON serialization
        resp = clean_for_json(resp)
        
        return jsonify(resp), 200
        
    except Exception as e:
        import traceback
        error_details = str(e)
        traceback_info = traceback.format_exc()
        print(f"ERROR in verify_fingerprint: {error_details}")
        print(f"Traceback: {traceback_info}")
        return jsonify({'error': 'Internal error', 'details': error_details}), 500

@app.route('/health', methods=['GET'])
def health():
    ensure_dirs()
    return jsonify({
        'status': 'healthy',
        'matching_method': 'Simple fingerprint verification with template matching',
        'visual_threshold': VISUAL_MATCH_THRESHOLD,
        'top2_margin': TOP2_MARGIN,
        'min_image_quality': MIN_IMAGE_QUALITY,
        'temp_folder': TEMP_FOLDER,
        'timestamp': datetime.now().isoformat()
    })

if __name__ == '__main__':
    ensure_dirs()
    print('Starting Fixed Fingerprint Verification Server...')
    print(f'Threshold: {VISUAL_MATCH_THRESHOLD}, Top2 margin: {TOP2_MARGIN}, Min quality: {MIN_IMAGE_QUALITY}')
    app.run(host='0.0.0.0', port=int(os.getenv('PORT', 5001)), debug=False)
