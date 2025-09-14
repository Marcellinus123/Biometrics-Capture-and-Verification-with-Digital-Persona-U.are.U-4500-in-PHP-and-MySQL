import os
import cv2
import tempfile

def test_file_operations():
    """Test the file operations that the Flask server does"""
    
    # Test temp folder creation
    temp_folder = 'temp_fingerprints'
    if not os.path.exists(temp_folder):
        os.makedirs(temp_folder, exist_ok=True)
        print(f"Created temp folder: {temp_folder}")
    else:
        print(f"Temp folder exists: {temp_folder}")
    
    # Test file saving and reading
    test_image_data = b'\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x08\x00\x00\x00\x08\x08\x02\x00\x00\x00\xc4\x0f\xfc\x8d\x00\x00\x00\tpHYs\x00\x00\x0b\x13\x00\x00\x0b\x13\x01\x00\x9a\x9c\x18\x00\x00\x00\x0cIDATx\x9cc\xf8\x0f\x00\x00\x02\x01\x01\x00\x18\xdd\x8d\xb0\x00\x00\x00\x00IEND\xaeB`\x82'
    
    # Save test file
    temp_path = os.path.join(temp_folder, f"probe_{123456789}.png")
    with open(temp_path, 'wb') as f:
        f.write(test_image_data)
    
    print(f"Saved test file: {temp_path}")
    print(f"File exists: {os.path.exists(temp_path)}")
    print(f"File size: {os.path.getsize(temp_path)} bytes")
    
    # Try to read with OpenCV
    probe = cv2.imread(temp_path, cv2.IMREAD_GRAYSCALE)
    if probe is None:
        print("ERROR: cv2.imread returned None!")
        print(f"Current working directory: {os.getcwd()}")
        print(f"Absolute path: {os.path.abspath(temp_path)}")
        
        # Try with absolute path
        abs_path = os.path.abspath(temp_path)
        probe = cv2.imread(abs_path, cv2.IMREAD_GRAYSCALE)
        if probe is None:
            print("ERROR: Even absolute path failed!")
        else:
            print("SUCCESS: Absolute path worked!")
            print(f"Image shape: {probe.shape}")
    else:
        print(f"SUCCESS: Image loaded with shape: {probe.shape}")
    
    # Clean up
    if os.path.exists(temp_path):
        os.unlink(temp_path)
        print(f"Cleaned up: {temp_path}")

if __name__ == "__main__":
    test_file_operations()

