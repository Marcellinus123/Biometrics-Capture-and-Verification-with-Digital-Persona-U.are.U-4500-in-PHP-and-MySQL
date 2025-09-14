import os
import cv2
import time
import base64
import mysql.connector
import numpy as np
from flask import Flask, request, jsonify
from datetime import datetime
from sklearn.metrics.pairwise import cosine_similarity
import hashlib

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

# New accuracy enhancement parameters
SIFT_FEATURE_THRESHOLD = float(os.getenv('SIFT_FEATURE_THRESHOLD', '0.25'))
MULTI_SAMPLE_CONSENSUS = int(os.getenv('MULTI_SAMPLE_CONSENSUS', '3'))
ROTATION_TOLERANCE = int(os.getenv('ROTATION_TOLERANCE', '15'))  # degrees
ENHANCED_PREPROCESSING = os.getenv('ENHANCED_PREPROCESSING', 'true').lower() == 'true'

# Multi-sample consensus storage (enhanced)
CONSENSUS_BUFFER = {}
CONSENSUS_WINDOW_SECONDS = 300  # 5 minutes
CONSENSUS_MIN_SAMPLES = MULTI_SAMPLE_CONSENSUS

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
        # Get image dimensions for debugging
        height, width = gray.shape
        print(f"DEBUG: Image dimensions: {width}x{height}")
        
        gray = cv2.GaussianBlur(gray, (3, 3), 0)
        lap_var = cv2.Laplacian(gray, cv2.CV_64F).var()
        sharpness = min(1.0, lap_var / 500.0)
        contrast = float(gray.std() / 128.0)
        contrast = max(0.0, min(1.0, contrast))
        
        quality = round(0.6 * sharpness + 0.4 * contrast, 3)
        print(f"DEBUG: Quality assessment - Sharpness: {sharpness:.3f}, Contrast: {contrast:.3f}, Final: {quality:.3f}")
        
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

def enhanced_preprocess(gray: np.ndarray) -> np.ndarray:
    """Enhanced preprocessing with multiple techniques"""
    if gray is None:
        return None
    
    try:
        # 1. Noise reduction with bilateral filter
        denoised = cv2.bilateralFilter(gray, 9, 75, 75)
        
        # 2. Histogram equalization for better contrast
        clahe = cv2.createCLAHE(clipLimit=3.0, tileGridSize=(8, 8))
        enhanced = clahe.apply(denoised)
        
        # 3. Morphological operations to clean up
        kernel = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (3, 3))
        cleaned = cv2.morphologyEx(enhanced, cv2.MORPH_CLOSE, kernel)
        
        # 4. Edge enhancement
        laplacian = cv2.Laplacian(cleaned, cv2.CV_64F)
        sharpened = cleaned - 0.5 * laplacian
        sharpened = np.clip(sharpened, 0, 255).astype(np.uint8)
        
        return sharpened
    except Exception as e:
        print(f"DEBUG: Enhanced preprocessing error: {e}")
        return gray

def extract_sift_features(gray: np.ndarray) -> tuple:
    """Extract SIFT features for advanced matching"""
    try:
        # Initialize SIFT detector
        sift = cv2.SIFT_create(
            nfeatures=1000,
            nOctaveLayers=3,
            contrastThreshold=0.04,
            edgeThreshold=10,
            sigma=1.6
        )
        
        # Detect keypoints and compute descriptors
        keypoints, descriptors = sift.detectAndCompute(gray, None)
        
        if descriptors is None:
            return [], None
        
        return keypoints, descriptors
    except Exception as e:
        print(f"DEBUG: SIFT extraction error: {e}")
        return [], None

def compute_sift_similarity(desc1, desc2) -> float:
    """Compute similarity between SIFT descriptors using FLANN matcher"""
    try:
        if desc1 is None or desc2 is None or len(desc1) == 0 or len(desc2) == 0:
            return 0.0
        
        # FLANN parameters for fast matching
        FLANN_INDEX_KDTREE = 1
        index_params = dict(algorithm=FLANN_INDEX_KDTREE, trees=5)
        search_params = dict(checks=50)
        
        # Create FLANN matcher
        flann = cv2.FlannBasedMatcher(index_params, search_params)
        
        # Find matches
        matches = flann.knnMatch(desc1, desc2, k=2)
        
        # Apply Lowe's ratio test
        good_matches = []
        for match_pair in matches:
            if len(match_pair) == 2:
                m, n = match_pair
                if m.distance < 0.7 * n.distance:
                    good_matches.append(m)
        
        # Calculate similarity score
        if len(matches) > 0:
            similarity = len(good_matches) / len(matches)
            return min(1.0, similarity)
        
        return 0.0
    except Exception as e:
        print(f"DEBUG: SIFT similarity error: {e}")
        return 0.0

def compute_rotation_invariant_similarity(img1: np.ndarray, img2: np.ndarray) -> float:
    """Compute similarity with rotation tolerance"""
    try:
        best_score = 0.0
        
        # Test multiple rotations
        for angle in range(-ROTATION_TOLERANCE, ROTATION_TOLERANCE + 1, 5):
            # Rotate image
            height, width = img1.shape
            center = (width // 2, height // 2)
            rotation_matrix = cv2.getRotationMatrix2D(center, angle, 1.0)
            rotated = cv2.warpAffine(img1, rotation_matrix, (width, height))
            
            # Compute template matching score
            result = cv2.matchTemplate(rotated, img2, cv2.TM_CCOEFF_NORMED)
            score = float(np.max(result))
            best_score = max(best_score, score)
    
        return float(best_score)
    except Exception as e:
        print(f"DEBUG: Rotation invariant similarity error: {e}")
        return 0.0

def compute_enhanced_similarity(probe: np.ndarray, student: np.ndarray) -> dict:
    """Compute multiple similarity metrics for enhanced accuracy"""
    try:
        results = {}
        
        # 1. Basic template matching
        result = cv2.matchTemplate(probe, student, cv2.TM_CCOEFF_NORMED)
        results['template_score'] = float(np.max(result))
        
        # 2. SIFT feature matching
        probe_kp, probe_desc = extract_sift_features(probe)
        student_kp, student_desc = extract_sift_features(student)
        results['sift_score'] = compute_sift_similarity(probe_desc, student_desc)
        
        # 3. Rotation invariant matching
        results['rotation_score'] = compute_rotation_invariant_similarity(probe, student)
        
        # 4. Histogram comparison
        hist1 = cv2.calcHist([probe], [0], None, [256], [0, 256])
        hist2 = cv2.calcHist([student], [0], None, [256], [0, 256])
        hist_similarity = cv2.compareHist(hist1, hist2, cv2.HISTCMP_CORREL)
        results['histogram_score'] = float(max(0.0, (hist_similarity + 1) / 2))  # Normalize to 0-1
        
        # 5. Structural similarity (SSIM-like)
        probe_norm = cv2.normalize(probe, None, 0, 1, cv2.NORM_MINMAX, cv2.CV_32F)
        student_norm = cv2.normalize(student, None, 0, 1, cv2.NORM_MINMAX, cv2.CV_32F)
        ssim_score = np.mean(probe_norm * student_norm)
        results['structural_score'] = float(ssim_score)
        
        # 6. Weighted combined score
        weights = {
            'template_score': 0.35,
            'sift_score': 0.25,
            'rotation_score': 0.20,
            'histogram_score': 0.10,
            'structural_score': 0.10
        }
        
        combined_score = sum(results[key] * weights[key] for key in weights.keys())
        results['combined_score'] = float(combined_score)
        
        return results
        
    except Exception as e:
        print(f"DEBUG: Enhanced similarity computation error: {e}")
        return {'combined_score': 0.0}

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
        
        # Debug: Show what we found
        for student in students:
            print(f"DEBUG: Student {student['student_id']}: {student['full_name']} - Data type: {type(student['fingerprint_data'])}")
            if isinstance(student['fingerprint_data'], str):
                print(f"DEBUG:   Data length: {len(student['fingerprint_data'])} chars")
                if student['fingerprint_data'].endswith('.png'):
                    print(f"DEBUG:   File path: {student['fingerprint_data']}")
                    if os.path.exists(student['fingerprint_data']):
                        print(f"DEBUG:   File exists and size: {os.path.getsize(student['fingerprint_data'])} bytes")
                    else:
                        print(f"DEBUG:   File does not exist!")
        
        cursor.close()
        conn.close()
        return students
    except Exception as e:
        print(f"DEBUG: Error fetching students: {e}")
        if conn:
            conn.close()
        return []

def compare_probe_to_all(probe: np.ndarray, students: list) -> list:
    """Compare probe fingerprint to all enrolled students using enhanced techniques"""
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
            
            # Enhanced preprocessing if enabled
            if ENHANCED_PREPROCESSING:
                probe_proc = enhanced_preprocess(probe)
                student_proc = enhanced_preprocess(student_fp)
                print(f"DEBUG:   Enhanced preprocessing applied")
            else:
                probe_proc = preprocess(probe)
                student_proc = preprocess(student_fp)
            
            print(f"DEBUG:   Preprocessed - Probe: {probe_proc.shape}, Student: {student_proc.shape}")
            
            # Enhanced similarity computation
            if ENHANCED_PREPROCESSING:
                similarity_results = compute_enhanced_similarity(probe_proc, student_proc)
                score = similarity_results['combined_score']
                
                print(f"DEBUG:   Enhanced scores - Template: {similarity_results['template_score']:.4f}, SIFT: {similarity_results['sift_score']:.4f}")
                print(f"DEBUG:   Rotation: {similarity_results['rotation_score']:.4f}, Histogram: {similarity_results['histogram_score']:.4f}")
                print(f"DEBUG:   Structural: {similarity_results['structural_score']:.4f}, Combined: {score:.4f}")
                
                # Store detailed scores for debugging - ensure all values are JSON serializable
                clean_similarity_results = {}
                for key, value in similarity_results.items():
                    if isinstance(value, (np.integer, np.floating)):
                        clean_similarity_results[key] = float(value)
                    else:
                        clean_similarity_results[key] = value
                
                results.append({
                    'student_id': student['student_id'],
                    'full_name': student['full_name'],
                    'score': float(score),
                    'detailed_scores': clean_similarity_results
                })
            else:
                # Fallback to simple template matching
                result = cv2.matchTemplate(probe_proc, student_proc, cv2.TM_CCOEFF_NORMED)
                score = float(np.max(result))  # Convert to regular Python float
                print(f"DEBUG:   Basic template score: {score:.4f}")
                
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

def consensus_update(session_id: str, candidate: dict):
    """Update consensus buffer with new candidate for enhanced multi-sample verification"""
    if session_id not in CONSENSUS_BUFFER:
        CONSENSUS_BUFFER[session_id] = {
            'samples': [],
            'ts': time.time(),
            'consensus_count': 0
        }
    
    # Add new sample
    CONSENSUS_BUFFER[session_id]['samples'].append({
        'candidate': candidate,
        'timestamp': time.time(),
        'sample_id': len(CONSENSUS_BUFFER[session_id]['samples']) + 1
    })
    
    # Keep only recent samples within window
    current_time = time.time()
    CONSENSUS_BUFFER[session_id]['samples'] = [
        s for s in CONSENSUS_BUFFER[session_id]['samples']
        if current_time - s['timestamp'] <= CONSENSUS_WINDOW_SECONDS
    ]
    
    print(f"DEBUG: Consensus buffer updated for session {session_id} - {len(CONSENSUS_BUFFER[session_id]['samples'])} samples")

def check_consensus(session_id: str, current_candidate: dict) -> tuple:
    """Check if current candidate matches previous samples for consensus"""
    if session_id not in CONSENSUS_BUFFER:
        return False, "No previous samples"
    
    session_data = CONSENSUS_BUFFER[session_id]
    samples = session_data['samples']
    
    if len(samples) < CONSENSUS_MIN_SAMPLES:
        return False, f"Need {CONSENSUS_MIN_SAMPLES} samples, have {len(samples)}"
    
    # Check if current candidate matches previous samples
    matching_samples = 0
    total_samples = len(samples)
    
    for sample in samples:
        if sample['candidate']['student_id'] == current_candidate['student_id']:
            matching_samples += 1
    
    consensus_ratio = matching_samples / total_samples
    consensus_passed = consensus_ratio >= 0.8  # 80% consensus required
    
    print(f"DEBUG: Consensus check - {matching_samples}/{total_samples} samples match ({consensus_ratio:.2%})")
    
    return consensus_passed, f"Consensus {'passed' if consensus_passed else 'failed'} ({consensus_ratio:.2%})"

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
            return jsonify({'error': 'No enrolled fingerprints'}), 404
        
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
        
        # Enhanced multi-sample consensus
        session_id = request.headers.get('X-Session-Id')
        sample_index = request.headers.get('X-Sample-Index')
        consensus_ok = True
        consensus_note = None
        
        if session_id:
            if sample_index:
                # Add current sample to consensus buffer
                consensus_update(session_id, best)
                
                # Check if we have enough samples for consensus
                consensus_passed, consensus_message = check_consensus(session_id, best)
                
                if consensus_passed:
                    consensus_ok = True
                    consensus_note = f"Multi-sample consensus passed ({consensus_message})"
                else:
                    consensus_ok = False
                    consensus_note = f"Multi-sample consensus pending ({consensus_message})"
            else:
                # Single sample mode - use basic consensus
                consensus_ok = True
                consensus_note = "Single sample verification"
        else:
            # No session ID - single verification
            consensus_ok = True
            consensus_note = "Direct verification"
        
        accepted = is_match and consensus_ok
        
        resp = {
            'match': accepted,
            'best': best,
            'second_best_score': second['score'],
            'margin': margin,
            'quality_score': quality,
            'threshold': VISUAL_MATCH_THRESHOLD,
            'top2_margin_required': TOP2_MARGIN,
            'total_compared': len(results),
            'method': 'Enhanced fingerprint verification with multiple algorithms',
            'timestamp': datetime.now().isoformat(),
            'enhanced_features': {
                'sift_enabled': ENHANCED_PREPROCESSING,
                'rotation_invariant': ENHANCED_PREPROCESSING,
                'multi_sample_consensus': MULTI_SAMPLE_CONSENSUS,
                'enhanced_preprocessing': ENHANCED_PREPROCESSING
            }
        }
        
        # Add detailed scores if available
        if 'detailed_scores' in best:
            resp['detailed_analysis'] = best['detailed_scores']
        
        if session_id:
            resp['consensus'] = {
                'session_id': session_id,
                'sample_index': sample_index,
                'note': consensus_note,
            }
        
        # include top-3 for debugging
        resp['top_candidates'] = results[:3]
        
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
        'matching_method': 'Enhanced fingerprint verification with multiple algorithms',
        'visual_threshold': VISUAL_MATCH_THRESHOLD,
        'top2_margin': TOP2_MARGIN,
        'min_image_quality': MIN_IMAGE_QUALITY,
        'enhanced_features': {
            'sift_enabled': ENHANCED_PREPROCESSING,
            'sift_threshold': SIFT_FEATURE_THRESHOLD,
            'rotation_tolerance': ROTATION_TOLERANCE,
            'multi_sample_consensus': MULTI_SAMPLE_CONSENSUS,
            'enhanced_preprocessing': ENHANCED_PREPROCESSING
        },
        'temp_folder': TEMP_FOLDER,
        'timestamp': datetime.now().isoformat()
    })

@app.route('/clear_temp', methods=['POST', 'GET'])
def clear_temp():
    try:
        if os.path.exists(TEMP_FOLDER):
            import shutil
            shutil.rmtree(TEMP_FOLDER)
        ensure_dirs()
        return jsonify({'status': 'ok', 'message': 'Temp cleared'}), 200
    except Exception as e:
        return jsonify({'error': str(e)}), 500

if __name__ == '__main__':
    ensure_dirs()
    print('Starting Enhanced Fingerprint Verification Server...')
    print(f'Threshold: {VISUAL_MATCH_THRESHOLD}, Top2 margin: {TOP2_MARGIN}, Min quality: {MIN_IMAGE_QUALITY}')
    print(f'Enhanced Features: SIFT={ENHANCED_PREPROCESSING}, Rotation={ROTATION_TOLERANCE}°, Multi-sample={MULTI_SAMPLE_CONSENSUS}')
    app.run(host='0.0.0.0', port=int(os.getenv('PORT', 5000)), debug=False)
