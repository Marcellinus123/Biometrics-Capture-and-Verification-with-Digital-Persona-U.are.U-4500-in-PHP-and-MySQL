import cv2
import numpy as np
from matplotlib import pyplot as plt
import mysql.connector
from flask import Flask, request, jsonify
import os
import io
import base64
import hashlib
import time
from werkzeug.utils import secure_filename
from functools import wraps
import logging
from datetime import datetime

app = Flask(__name__)

# Enhanced logging configuration
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Database configuration with connection pooling
db_config = {
    'host': os.getenv('DB_HOST', 'localhost'),
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASSWORD', ''),
    'database': os.getenv('DB_NAME', 'bioattend_db_system'),
    'pool_name': 'fingerprint_pool',
    'pool_size': 5,
    'pool_reset_session': True
}

# Enhanced configuration - Made verification much less strict
UPLOAD_FOLDER = 'uploads'
TEMP_FOLDER = 'temp_fingerprints'
ALLOWED_EXTENSIONS = {'png', 'jpg', 'jpeg', 'bmp', 'tiff'}
THRESHOLD = float(os.getenv('MATCH_THRESHOLD', '0.1'))  # Reduced from 0.4 to 0.1 for less strict matching
MAX_FILE_SIZE = 5 * 1024 * 1024  # 5MB
MIN_MATCHES = int(os.getenv('MIN_MATCHES', '5'))  # Minimum 5 matches for verification

app.config['UPLOAD_FOLDER'] = UPLOAD_FOLDER
app.config['MAX_CONTENT_LENGTH'] = MAX_FILE_SIZE

# Rate limiting dictionary (in production, use Redis)
request_counts = {}


def rate_limit(max_requests=10, window=60):
    """Simple rate limiting decorator"""
    def decorator(f):
        @wraps(f)
        def decorated_function(*args, **kwargs):
            client_ip = request.remote_addr
            current_time = time.time()
            
            if client_ip not in request_counts:
                request_counts[client_ip] = []
            
            # Clean old requests
            request_counts[client_ip] = [
                req_time for req_time in request_counts[client_ip]
                if current_time - req_time < window
            ]
            
            if len(request_counts[client_ip]) >= max_requests:
                return jsonify({'error': 'Rate limit exceeded'}), 429
            
            request_counts[client_ip].append(current_time)
            return f(*args, **kwargs)
        return decorated_function
    return decorator

def get_db_connection():
    """Create and return a database connection with better error handling"""
    try:
        conn = mysql.connector.connect(**db_config)
        return conn
    except mysql.connector.Error as err:
        logger.error(f"Database connection error: {err}")
        return None

def validate_image(img_data):
    """Validate uploaded image - made less strict"""
    if len(img_data) > MAX_FILE_SIZE:
        return False, "File too large"
    
    # Check if it's a valid image by trying to decode it
    try:
        nparr = np.frombuffer(img_data, np.uint8)
        img = cv2.imdecode(nparr, cv2.IMREAD_GRAYSCALE)
        if img is None:
            return False, "Invalid image format"
        
        # Check image dimensions - made less strict
        height, width = img.shape
        if height < 50 or width < 50:  # Reduced from 100x100
            return False, "Image too small (minimum 50x50 pixels)"
        if height > 3000 or width > 3000:  # Increased max size
            return False, "Image too large (maximum 3000x3000 pixels)"
            
        return True, "Valid"
    except Exception as e:
        return False, f"Image validation failed: {str(e)}"

def enhanced_preprocess_image(img):
    """Enhanced fingerprint preprocessing with quality assessment - less strict"""
    if img is None:
        return None, 0
    
    original_img = img.copy()
    
    # Resize if too large
    height, width = img.shape
    if max(height, width) > 800:
        scale = 800 / max(height, width)
        new_width = int(width * scale)
        new_height = int(height * scale)
        img = cv2.resize(img, (new_width, new_height), interpolation=cv2.INTER_AREA)
    
    # Quality assessment based on variance - more lenient
    quality_score = cv2.Laplacian(img, cv2.CV_64F).var()
    
    # Enhanced normalization
    img = cv2.normalize(img, None, 0, 255, cv2.NORM_MINMAX)
    
    # Adaptive contrast enhancement - gentler settings
    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8,8))
    img = clahe.apply(img)
    
    # Gentler noise reduction
    img = cv2.bilateralFilter(img, 5, 50, 50)
    
    # Gentler edge enhancement
    kernel = np.array([[-0.5,-0.5,-0.5], [-0.5,5,-0.5], [-0.5,-0.5,-0.5]])
    img = cv2.filter2D(img, -1, kernel)
    
    return img, quality_score

def advanced_feature_matching(des1, des2, algorithm="ORB"):
    """Advanced feature matching with multiple algorithms - made less strict"""
    if des1 is None or des2 is None or len(des1) == 0 or len(des2) == 0:
        return [], 0
    
    good_matches = []
    confidence = 0
    
    try:
        if algorithm == "ORB":
            # Enhanced ORB matching - more lenient
            bf = cv2.BFMatcher(cv2.NORM_HAMMING, crossCheck=True)
            matches = bf.match(des1, des2)
            matches = sorted(matches, key=lambda x: x.distance)
            
            # More lenient filtering for good matches
            if len(matches) > 0:
                # Use top 70% of matches instead of strict statistical filtering
                num_good = int(len(matches) * 0.7)
                good_matches = matches[:num_good] if num_good > 0 else matches[:1]
                
        else:  # SIFT
            # FLANN matcher for SIFT - more lenient
            FLANN_INDEX_KDTREE = 1
            index_params = dict(algorithm=FLANN_INDEX_KDTREE, trees=5)
            search_params = dict(checks=50)
            flann = cv2.FlannBasedMatcher(index_params, search_params)
            
            matches = flann.knnMatch(des1, des2, k=2)
            
            # More lenient ratio test
            for match_pair in matches:
                if len(match_pair) == 2:
                    m, n = match_pair
                    if m.distance < 0.8 * n.distance:  # Increased from 0.7 to 0.8
                        good_matches.append(m)
    
    except Exception as e:
        logger.error(f"Matching error: {e}")
    
    return good_matches, len(good_matches)

def match_fingerprints_advanced(img1, img2, min_confidence=THRESHOLD):
    """Advanced fingerprint matching with quality metrics - much less strict"""
    start_time = time.time()
    
    try:
        # Enhanced preprocessing
        img1_processed, quality1 = enhanced_preprocess_image(img1)
        img2_processed, quality2 = enhanced_preprocess_image(img2)
        
        if img1_processed is None or img2_processed is None:
            return {"error": "Image preprocessing failed"}
        
        # Very lenient quality threshold
        min_quality = 20  # Reduced from 100
        if quality1 < min_quality and quality2 < min_quality:
            logger.warning(f"Very low quality images: {quality1}, {quality2}")
        
        results = {}
        best_confidence = 0
        best_matches = 0
        best_algorithm = None
        
        # Try multiple algorithms
        algorithms = [
            ("ORB", cv2.ORB_create(nfeatures=1500)),  # More features
            ("SIFT", cv2.SIFT_create(nfeatures=800))
        ]
        
        for alg_name, detector in algorithms:
            try:
                kp1, des1 = detector.detectAndCompute(img1_processed, None)
                kp2, des2 = detector.detectAndCompute(img2_processed, None)
                
                if des1 is not None and des2 is not None:
                    good_matches, match_count = advanced_feature_matching(des1, des2, alg_name)
                    
                    # Calculate confidence - more lenient
                    min_keypoints = max(10, min(len(kp1), len(kp2)))  # Use at least 10 as denominator
                    confidence = match_count / min_keypoints
                    
                    results[alg_name] = {
                        "confidence": confidence,
                        "matches": match_count,
                        "keypoints": f"{len(kp1)} vs {len(kp2)}"
                    }
                    
                    if match_count > best_matches:
                        best_matches = match_count
                        best_confidence = confidence
                        best_algorithm = alg_name
                        
            except Exception as e:
                logger.error(f"{alg_name} failed: {e}")
                continue
        
        # Much more lenient matching criteria
        is_match = (best_matches >= MIN_MATCHES and best_confidence >= min_confidence) or best_matches >= 10
        
        processing_time = time.time() - start_time
        
        return {
            "match": is_match,
            "confidence": best_confidence,
            "matches": best_matches,
            "threshold": min_confidence,
            "algorithm": best_algorithm,
            "quality_scores": {"img1": quality1, "img2": quality2},
            "processing_time": round(processing_time, 3),
            "detailed_results": results
        }
        
    except Exception as e:
        logger.error(f"Matching error: {e}")
        return {"error": str(e)}

def allowed_file(filename):
    return '.' in filename and \
           filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS

def get_student_fingerprints_cached():
    """Retrieve student fingerprints with caching mechanism - fixed file saving"""
    cache_file = 'fingerprint_cache.json'
    cache_timeout = 3600  # 1 hour
    
    # Check if cache exists and is valid
    if os.path.exists(cache_file):
        cache_age = time.time() - os.path.getmtime(cache_file)
        if cache_age < cache_timeout:
            try:
                import json
                with open(cache_file, 'r') as f:
                    cached_data = json.load(f)
                    # Verify cached files still exist
                    valid_cache = True
                    for student in cached_data:
                        if not os.path.exists(student.get('fingerprint_path', '')):
                            valid_cache = False
                            break
                    if valid_cache:
                        return cached_data
            except:
                pass
    
    # Fetch from database
    conn = get_db_connection()
    if not conn:
        return None
    
    try:
        cursor = conn.cursor(dictionary=True)
        query = """
        SELECT student_id, student_number, full_name, fingerprint_data, 
               created_at, updated_at 
        FROM students 
        WHERE fingerprint_data IS NOT NULL 
        AND fingerprint_data != '' 
        AND is_active = 1
        """
        cursor.execute(query)
        students = cursor.fetchall()
        
        # Create temporary directory if it doesn't exist
        if not os.path.exists(TEMP_FOLDER):
            os.makedirs(TEMP_FOLDER, exist_ok=True)
            logger.info(f"Created temporary folder: {TEMP_FOLDER}")
        
        # Process fingerprint data
        processed_students = []
        for student in students:
            try:
                # Use student_number or student_id for filename (no hashing)
                filename = f"student_{student['student_number']}.png" if student['student_number'] else f"student_{student['student_id']}.png"
                fingerprint_path = os.path.join(TEMP_FOLDER, filename)
                
                # Decode and save fingerprint to temp folder
                fingerprint_data = base64.b64decode(student['fingerprint_data'])
                
                # Ensure we can write to the temp folder
                with open(fingerprint_path, 'wb') as f:
                    f.write(fingerprint_data)
                
                # Verify the file was created successfully
                if os.path.exists(fingerprint_path) and os.path.getsize(fingerprint_path) > 0:
                    student['fingerprint_path'] = fingerprint_path
                    processed_students.append(student)
                    logger.info(f"Successfully saved fingerprint for student {student['student_id']} to {fingerprint_path}")
                else:
                    logger.error(f"Failed to save fingerprint for student {student['student_id']}")
                
            except Exception as e:
                logger.error(f"Error processing student {student['student_id']}: {e}")
                continue
        
        # Cache the results
        try:
            import json
            with open(cache_file, 'w') as f:
                # Cache the metadata
                cache_data = [{
                    'student_id': s['student_id'],
                    'student_number': s['student_number'],
                    'full_name': s['full_name'],
                    'fingerprint_path': s['fingerprint_path'],
                    'created_at': s['created_at'].isoformat() if s['created_at'] else None,
                    'updated_at': s['updated_at'].isoformat() if s['updated_at'] else None
                } for s in processed_students]
                json.dump(cache_data, f, indent=2)
                logger.info(f"Cached {len(cache_data)} student fingerprints")
        except Exception as e:
            logger.error(f"Failed to cache fingerprint data: {e}")
        
        return processed_students
        
    except Exception as e:
        logger.error(f"Error fetching student fingerprints: {e}")
        return None
    finally:
        if conn and conn.is_connected():
            conn.close()

def log_verification_attempt(student_id, success, confidence, ip_address, matches=0):
    """Log verification attempts for audit purposes"""
    conn = get_db_connection()
    if not conn:
        return
    
    try:
        cursor = conn.cursor()
        
        # Check if verification_logs table exists, create if not
        cursor.execute("""
        CREATE TABLE IF NOT EXISTS verification_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT,
            success BOOLEAN,
            confidence DECIMAL(5,4),
            matches INT DEFAULT 0,
            ip_address VARCHAR(45),
            timestamp DATETIME
        )
        """)
        
        query = """
        INSERT INTO verification_logs 
        (student_id, success, confidence, matches, ip_address, timestamp) 
        VALUES (%s, %s, %s, %s, %s, %s)
        """
        cursor.execute(query, (student_id, success, confidence, matches, ip_address, datetime.now()))
        conn.commit()
    except Exception as e:
        logger.error(f"Error logging verification: {e}")
    finally:
        if conn and conn.is_connected():
            conn.close()

@app.route('/verify', methods=['POST'])
@rate_limit(max_requests=20, window=60)
def verify_fingerprint():
    """Enhanced API endpoint for fingerprint verification - less strict"""
    try:
        # Validate request
        if 'fingerprint' not in request.files:
            return jsonify({"error": "No fingerprint file provided"}), 400
        
        file = request.files['fingerprint']
        
        if file.filename == '':
            return jsonify({"error": "No selected file"}), 400
        
        if not allowed_file(file.filename):
            return jsonify({"error": "Invalid file type"}), 400
        
        # Read and validate file
        file_data = file.read()
        is_valid, message = validate_image(file_data)
        if not is_valid:
            return jsonify({"error": message}), 400
        
        # Load uploaded image
        nparr = np.frombuffer(file_data, np.uint8)
        uploaded_img = cv2.imdecode(nparr, cv2.IMREAD_GRAYSCALE)
        
        if uploaded_img is None:
            return jsonify({"error": "Could not decode the uploaded image"}), 400
        
        # Get student fingerprints
        students = get_student_fingerprints_cached()
        if not students:
            return jsonify({"error": "No student fingerprints available or temp folder access failed"}), 404
        
        best_match = None
        highest_confidence = 0
        most_matches = 0
        verification_results = []
        
        logger.info(f"Comparing against {len(students)} stored fingerprints")
        
        # Compare against all stored fingerprints
        for student in students:
            try:
                fingerprint_path = student.get('fingerprint_path')
                if not fingerprint_path or not os.path.exists(fingerprint_path):
                    logger.warning(f"Fingerprint file not found for student {student['student_id']}: {fingerprint_path}")
                    continue
                    
                stored_img = cv2.imread(fingerprint_path, cv2.IMREAD_GRAYSCALE)
                
                if stored_img is None:
                    logger.warning(f"Could not load fingerprint image for student {student['student_id']}")
                    continue
                
                # Perform advanced matching
                result = match_fingerprints_advanced(uploaded_img, stored_img)
                
                if 'error' in result:
                    logger.warning(f"Matching error for student {student['student_id']}: {result['error']}")
                    continue
                
                verification_results.append({
                    "student_id": student['student_id'],
                    "confidence": result['confidence'],
                    "matches": result.get('matches', 0),
                    "match": result['match']
                })
                
                # Track the best match (prioritize match count over confidence)
                if result['match'] and result.get('matches', 0) >= most_matches:
                    if result.get('matches', 0) > most_matches or result['confidence'] > highest_confidence:
                        most_matches = result.get('matches', 0)
                        highest_confidence = result['confidence']
                        best_match = {
                            "student_id": student['student_id'],
                            "student_number": student['student_number'],
                            "full_name": student['full_name'],
                            "confidence": result['confidence'],
                            "matches": result.get('matches', 0),
                            "algorithm": result['algorithm'],
                            "processing_time": result['processing_time'],
                            "quality_scores": result['quality_scores']
                        }
            
            except Exception as e:
                logger.error(f"Error processing student {student['student_id']}: {e}")
                continue
        
        # Log the verification attempt
        if best_match:
            log_verification_attempt(
                best_match['student_id'], 
                True, 
                highest_confidence, 
                request.remote_addr,
                most_matches
            )
            
            response = {
                "status": "success",
                "match": True,
                "student": best_match,
                "total_comparisons": len(students),
                "timestamp": datetime.now().isoformat()
            }
        else:
            log_verification_attempt(None, False, 0, request.remote_addr, 0)
            response = {
                "status": "success",
                "match": False,
                "message": "No matching fingerprint found",
                "total_comparisons": len(students),
                "verification_results": verification_results[:5],  # Show top 5 results for debugging
                "timestamp": datetime.now().isoformat()
            }
        
        return jsonify(response)
    
    except Exception as e:
        logger.error(f"Verification error: {e}")
        return jsonify({"error": "Internal server error", "details": str(e)}), 500

@app.route('/health', methods=['GET'])
def health_check():
    """Enhanced health check endpoint"""
    try:
        # Check database connection
        conn = get_db_connection()
        db_status = "connected" if conn else "disconnected"
        if conn and conn.is_connected():
            conn.close()
        
        # Check file system
        upload_status = "ok" if os.path.exists(UPLOAD_FOLDER) else "missing"
        temp_status = "ok" if os.path.exists(TEMP_FOLDER) else "missing"
        
        # Check temp folder contents
        temp_files = 0
        if os.path.exists(TEMP_FOLDER):
            temp_files = len([f for f in os.listdir(TEMP_FOLDER) if f.endswith('.png')])
        
        return jsonify({
            "status": "healthy",
            "database": db_status,
            "upload_folder": upload_status,
            "temp_folder": temp_status,
            "temp_fingerprints": temp_files,
            "threshold": THRESHOLD,
            "min_matches": MIN_MATCHES,
            "version": "2.1",
            "timestamp": datetime.now().isoformat()
        })
    except Exception as e:
        return jsonify({
            "status": "unhealthy",
            "error": str(e)
        }), 500

@app.route('/stats', methods=['GET'])
def get_verification_stats():
    """Get verification statistics"""
    conn = get_db_connection()
    if not conn:
        return jsonify({"error": "Database connection failed"}), 500
    
    try:
        cursor = conn.cursor(dictionary=True)
        
        # Check if table exists
        cursor.execute("""
        CREATE TABLE IF NOT EXISTS verification_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT,
            success BOOLEAN,
            confidence DECIMAL(5,4),
            matches INT DEFAULT 0,
            ip_address VARCHAR(45),
            timestamp DATETIME
        )
        """)
        
        # Get stats for the last 24 hours
        query = """
        SELECT 
            COUNT(*) as total_attempts,
            SUM(success) as successful_attempts,
            AVG(confidence) as avg_confidence,
            AVG(matches) as avg_matches,
            COUNT(DISTINCT ip_address) as unique_ips
        FROM verification_logs 
        WHERE timestamp >= NOW() - INTERVAL 24 HOUR
        """
        cursor.execute(query)
        stats = cursor.fetchone()
        
        return jsonify({
            "stats": stats,
            "period": "24 hours",
            "timestamp": datetime.now().isoformat()
        })
        
    except Exception as e:
        logger.error(f"Stats error: {e}")
        return jsonify({"error": "Failed to retrieve stats"}), 500
    finally:
        if conn and conn.is_connected():
            conn.close()

@app.route('/clear_temp', methods=['POST'])
def clear_temp_folder():
    """Clear temporary fingerprint files (admin endpoint)"""
    try:
        if os.path.exists(TEMP_FOLDER):
            import shutil
            shutil.rmtree(TEMP_FOLDER)
            os.makedirs(TEMP_FOLDER, exist_ok=True)
            
            # Also clear cache
            cache_file = 'fingerprint_cache.json'
            if os.path.exists(cache_file):
                os.remove(cache_file)
            
            return jsonify({
                "status": "success",
                "message": "Temporary folder and cache cleared"
            })
        else:
            return jsonify({
                "status": "success",
                "message": "Temporary folder does not exist"
            })
    except Exception as e:
        return jsonify({
            "error": f"Failed to clear temp folder: {str(e)}"
        }), 500

@app.errorhandler(413)
def too_large(e):
    return jsonify({"error": "File too large"}), 413

@app.errorhandler(429)
def ratelimit_handler(e):
    return jsonify({"error": "Rate limit exceeded"}), 429

if __name__ == '__main__':
    # Create required directories
    for folder in [UPLOAD_FOLDER, TEMP_FOLDER]:
        if not os.path.exists(folder):
            os.makedirs(folder, exist_ok=True)
            logger.info(f"Created directory: {folder}")
    
    logger.info(f"Starting fingerprint verification server...")
    logger.info(f"Match threshold: {THRESHOLD}")
    logger.info(f"Minimum matches: {MIN_MATCHES}")
    logger.info(f"Temp folder: {TEMP_FOLDER}")
    
    # Production settings
    app.run(
        host='0.0.0.0', 
        port=int(os.getenv('PORT', 5000)),
        debug=os.getenv('DEBUG', 'False').lower() == 'true'
    )