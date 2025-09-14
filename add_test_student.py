import mysql.connector
import base64
import os

def add_test_student():
    """Add a test student with fingerprint data to the database"""
    
    # Database configuration
    DB_HOST = 'localhost'
    DB_USER = 'root'
    DB_PASSWORD = ''
    DB_NAME = 'bioattend_db_system'
    
    try:
        # Connect to database
        print("Connecting to database...")
        conn = mysql.connector.connect(
            host=DB_HOST,
            user=DB_USER,
            password=DB_PASSWORD,
            database=DB_NAME,
        )
        print("✅ Database connection successful!")
        
        # Create a test fingerprint image using PIL
        try:
            from PIL import Image
            import numpy as np
            
            # Create a simple fingerprint-like image
            img_array = np.random.randint(100, 200, (64, 64), dtype=np.uint8)
            img = Image.fromarray(img_array, mode='L')
            
            # Save as PNG
            test_fp_file = 'test_student_fingerprint.png'
            img.save(test_fp_file, 'PNG')
            
            # Read the file and convert to base64
            with open(test_fp_file, 'rb') as f:
                image_data = f.read()
            
            # Convert to base64
            base64_data = base64.b64encode(image_data).decode('utf-8')
            
            print(f"✅ Created test fingerprint image: {test_fp_file}")
            print(f"   Image size: {img.size}")
            print(f"   Base64 length: {len(base64_data)} characters")
            
        except ImportError:
            print("❌ PIL not available, using simple base64 data")
            # Fallback: create a simple base64 string
            base64_data = "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=="
        
        # Check if test student already exists
        cursor = conn.cursor()
        cursor.execute("SELECT COUNT(*) FROM students WHERE full_name = 'Test Student'")
        existing_count = cursor.fetchone()[0]
        
        if existing_count > 0:
            print("🔄 Test student already exists, updating fingerprint data...")
            cursor.execute("""
                UPDATE students 
                SET fingerprint_data = %s, updated_at = NOW()
                WHERE full_name = 'Test Student'
            """, (base64_data,))
        else:
            print("➕ Adding new test student...")
            cursor.execute("""
                INSERT INTO students (
                    student_number, registration_number, full_name, 
                    fingerprint_data, reported_status, is_active, created_at
                ) VALUES (
                    'TEST001', 'REG001', 'Test Student',
                    %s, 'Not Reported', 1, NOW()
                )
            """, (base64_data,))
        
        # Commit the changes
        conn.commit()
        print("✅ Test student added/updated successfully!")
        
        # Verify the student was added
        cursor.execute("SELECT student_id, full_name, fingerprint_data FROM students WHERE full_name = 'Test Student'")
        student = cursor.fetchone()
        
        if student:
            student_id, full_name, fingerprint_data = student
            print(f"📋 Student ID: {student_id}")
            print(f"📋 Name: {full_name}")
            print(f"📋 Fingerprint data length: {len(fingerprint_data) if fingerprint_data else 0} characters")
        
        cursor.close()
        conn.close()
        
        # Clean up test file
        if os.path.exists(test_fp_file):
            os.remove(test_fp_file)
            print(f"🧹 Cleaned up {test_fp_file}")
        
        print("\n🎉 Test student setup complete! You can now test the verification system.")
        
    except mysql.connector.Error as e:
        print(f"❌ Database error: {e}")
    except Exception as e:
        print(f"❌ General error: {e}")

if __name__ == "__main__":
    add_test_student()

