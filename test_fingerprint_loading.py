import mysql.connector
import cv2
import numpy as np
import base64
import os

def test_fingerprint_loading():
    """Test the fingerprint loading functionality"""
    
    # Database configuration
    DB_HOST = 'localhost'
    DB_USER = 'root'
    DB_PASSWORD = ''
    DB_NAME = 'bioattend_db_system'
    
    try:
        # Connect to database
        conn = mysql.connector.connect(
            host=DB_HOST,
            user=DB_USER,
            password=DB_PASSWORD,
            database=DB_NAME,
        )
        
        # Get students with fingerprint data
        cursor = conn.cursor(dictionary=True)
        cursor.execute("""
            SELECT student_id, full_name, fingerprint_data 
            FROM students 
            WHERE fingerprint_data IS NOT NULL AND fingerprint_data != ''
        """)
        students = cursor.fetchall()
        
        print(f"Found {len(students)} students with fingerprint data")
        
        for student in students:
            student_id = student['student_id']
            full_name = student['full_name']
            fingerprint_data = student['fingerprint_data']
            
            print(f"\n--- Student {student_id}: {full_name} ---")
            print(f"Data type: {type(fingerprint_data)}")
            print(f"Data length: {len(fingerprint_data) if isinstance(fingerprint_data, str) else 'N/A'}")
            
            # Test the load_gray_from_value function
            img = load_gray_from_value(fingerprint_data)
            
            if img is not None:
                print(f"✅ Image loaded successfully!")
                print(f"   Shape: {img.shape}")
                print(f"   Data type: {img.dtype}")
                print(f"   Min/Max values: {img.min()}/{img.max()}")
            else:
                print(f"❌ Failed to load image")
                
                # Debug the data
                if isinstance(fingerprint_data, str):
                    if fingerprint_data.endswith('.png'):
                        print(f"   File path: {fingerprint_data}")
                        if os.path.exists(fingerprint_data):
                            print(f"   File exists: Yes, size: {os.path.getsize(fingerprint_data)} bytes")
                        else:
                            print(f"   File exists: No")
                    else:
                        print(f"   Appears to be base64 data")
                        try:
                            # Try to decode base64
                            raw = base64.b64decode(fingerprint_data, validate=False)
                            print(f"   Base64 decoded length: {len(raw)} bytes")
                        except Exception as e:
                            print(f"   Base64 decode error: {e}")
        
        cursor.close()
        conn.close()
        
    except Exception as e:
        print(f"Error: {e}")

def load_gray_from_value(value: str) -> np.ndarray | None:
    """Copy of the function from finger_clean.py"""
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
    except Exception as e:
        print(f"   Error in load_gray_from_value: {e}")
        img = None
    return img

if __name__ == "__main__":
    test_fingerprint_loading()

