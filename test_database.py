import mysql.connector
import os

def test_database():
    """Test database connection and check for students"""
    
    # Database configuration (same as Flask server)
    DB_HOST = 'localhost'
    DB_USER = 'root'
    DB_PASSWORD = ''
    DB_NAME = 'bioattend_db_system'
    
    try:
        # Test connection
        print("Testing database connection...")
        conn = mysql.connector.connect(
            host=DB_HOST,
            user=DB_USER,
            password=DB_PASSWORD,
            database=DB_NAME,
        )
        print("✅ Database connection successful!")
        
        # Check if students table exists
        cursor = conn.cursor()
        cursor.execute("SHOW TABLES LIKE 'students'")
        tables = cursor.fetchall()
        
        if not tables:
            print("❌ Students table does not exist!")
            return
        
        print("✅ Students table exists")
        
        # Check total students
        cursor.execute("SELECT COUNT(*) FROM students")
        total_students = cursor.fetchone()[0]
        print(f"📊 Total students: {total_students}")
        
        # Check students with fingerprint data
        cursor.execute("SELECT COUNT(*) FROM students WHERE fingerprint_data IS NOT NULL AND fingerprint_data != ''")
        students_with_fp = cursor.fetchone()[0]
        print(f"📊 Students with fingerprint data: {students_with_fp}")
        
        # Show sample students
        cursor.execute("SELECT student_id, full_name, fingerprint_data FROM students LIMIT 5")
        students = cursor.fetchall()
        
        print("\n📋 Sample Students:")
        for student in students:
            student_id, full_name, fingerprint_data = student
            fp_status = "Has data" if fingerprint_data else "No data"
            if fingerprint_data:
                if isinstance(fingerprint_data, str):
                    if fingerprint_data.endswith('.png'):
                        fp_status = f"File path: {fingerprint_data}"
                        if os.path.exists(fingerprint_data):
                            fp_status += f" (exists, {os.path.getsize(fingerprint_data)} bytes)"
                        else:
                            fp_status += " (file not found)"
                    else:
                        fp_status = f"Base64 data ({len(fingerprint_data)} chars)"
                else:
                    fp_status = f"Data type: {type(fingerprint_data)}"
            
            print(f"  ID: {student_id}, Name: {full_name}, Fingerprint: {fp_status}")
        
        # Check table structure
        cursor.execute("DESCRIBE students")
        columns = cursor.fetchall()
        
        print("\n🏗️  Students Table Structure:")
        for column in columns:
            field, type_name, null, key, default, extra = column
            print(f"  {field}: {type_name} {'(NULL)' if null == 'YES' else '(NOT NULL)'}")
        
        cursor.close()
        conn.close()
        
    except mysql.connector.Error as e:
        print(f"❌ Database error: {e}")
    except Exception as e:
        print(f"❌ General error: {e}")

if __name__ == "__main__":
    test_database()

