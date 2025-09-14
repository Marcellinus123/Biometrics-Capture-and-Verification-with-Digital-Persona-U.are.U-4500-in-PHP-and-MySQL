import requests
import json
import os

def test_verify_endpoint():
    """Test the verify endpoint with the valid PNG image"""
    url = "http://127.0.0.1:5000/verify"
    
    test_file = 'test_fingerprint.png'
    
    if not os.path.exists(test_file):
        print(f"Error: Test file {test_file} not found!")
        return
    
    print(f"Testing with: {test_file}")
    print(f"File size: {os.path.getsize(test_file)} bytes")
    
    try:
        # Test with file upload
        with open(test_file, 'rb') as f:
            files = {'fingerprint': ('test_fingerprint.png', f, 'image/png')}
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

if __name__ == "__main__":
    test_verify_endpoint()

