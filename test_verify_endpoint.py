import requests
import json

def test_verify_endpoint():
    """Test the verify endpoint to identify 500 error cause"""
    url = "http://127.0.0.1:5000/verify"
    
    # Create a minimal test image (1x1 pixel PNG)
    test_image_data = b'\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x02\x00\x00\x00\x90wS\xde\x00\x00\x00\tpHYs\x00\x00\x0b\x13\x00\x00\x0b\x13\x01\x00\x9a\x9c\x18\x00\x00\x00\x0cIDATx\x9cc\xf8\x0f\x00\x00\x02\x01\x01\x00\x18\xdd\x8d\xb0\x00\x00\x00\x00IEND\xaeB`\x82'
    
    try:
        # Test with file upload
        files = {'fingerprint': ('test.png', test_image_data, 'image/png')}
        response = requests.post(url, files=files, timeout=30)
        
        print(f"Status Code: {response.status_code}")
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

if __name__ == "__main__":
    test_verify_endpoint()
