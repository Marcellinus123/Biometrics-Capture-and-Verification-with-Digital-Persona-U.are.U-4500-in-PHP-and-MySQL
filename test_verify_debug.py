import requests
import json
import base64
import os

def test_verify_endpoint():
    """Test the verify endpoint with better debugging"""
    url = "http://127.0.0.1:5000/verify"
    
    # Create a valid test PNG image (8x8 pixel)
    png_header = b'\x89PNG\r\n\x1a\n'
    ihdr_chunk = b'\x00\x00\x00\rIHDR\x00\x00\x00\x08\x00\x00\x00\x08\x08\x02\x00\x00\x00\xc4\x0f\xfc\x8d\x00\x00\x00\tpHYs\x00\x00\x0b\x13\x00\x00\x0b\x13\x01\x00\x9a\x9c\x18\x00\x00\x00\x0cIDATx\x9cc\xf8\x0f\x00\x00\x02\x01\x01\x00\x18\xdd\x8d\xb0\x00\x00\x00\x00IEND\xaeB`\x82'
    test_image_data = png_header + ihdr_chunk
    
    # Save test image to file first
    test_file = 'test_debug.png'
    with open(test_file, 'wb') as f:
        f.write(test_image_data)
    
    print(f"Created test image: {test_file}")
    print(f"File size: {len(test_image_data)} bytes")
    print(f"File exists: {os.path.exists(test_file)}")
    
    try:
        # Test with file upload
        with open(test_file, 'rb') as f:
            files = {'fingerprint': ('test_debug.png', f, 'image/png')}
            response = requests.post(url, files=files, timeout=30)
        
        print(f"\nStatus Code: {response.status_code}")
        print(f"Response Headers: {dict(response.headers)}")
        print(f"Response Content: {response.text}")
        
        if response.status_code == 200:
            try:
                data = response.json()
                print(f"Success Response: {json.dumps(data, indent=2)}")
            except:
                print(f"Response Text: {response.text}")
        else:
            print(f"Error Response: {response.text}")
            
    except Exception as e:
        print(f"Request failed: {e}")
    finally:
        # Clean up
        if os.path.exists(test_file):
            os.remove(test_file)
            print(f"Cleaned up {test_file}")

if __name__ == "__main__":
    test_verify_endpoint()

