import requests
import json
import os

def test_with_real_fingerprint():
    """Test the verify endpoint with a real fingerprint image"""
    url = "http://127.0.0.1:5000/verify"  # Updated to use port 5000
    
    # Use one of the existing fingerprint files
    fingerprint_file = 'students_fingerprint/4.png'
    
    if not os.path.exists(fingerprint_file):
        print(f"Error: Fingerprint file {fingerprint_file} not found!")
        return
    
    print(f"Testing with real fingerprint: {fingerprint_file}")
    print(f"File size: {os.path.getsize(fingerprint_file)} bytes")
    
    try:
        # Test with file upload
        with open(fingerprint_file, 'rb') as f:
            files = {'fingerprint': ('real_fingerprint.png', f, 'image/png')}
            response = requests.post(url, files=files, timeout=30)
        
        print(f"\nStatus Code: {response.status_code}")
        print(f"Response Headers: {dict(response.headers)}")
        print(f"Response Content: {response.text}")
        
        if response.status_code == 200:
            try:
                data = response.json()
                print(f"Success Response: {json.dumps(data, indent=2)}")
                
                if data.get('match'):
                    print("🎉 SUCCESS: Fingerprint matched!")
                else:
                    print("ℹ️ No match found, but verification completed successfully")
                    
            except:
                print(f"Response Text: {response.text}")
        else:
            print(f"Error Response: {response.text}")
            
    except Exception as e:
        print(f"Request failed: {e}")

if __name__ == "__main__":
    test_with_real_fingerprint()
