#!/usr/bin/env python3
"""
Simple test script for the BioAttend Fingerprint API
"""

import requests
import json

def test_health():
    """Test the health endpoint"""
    try:
        response = requests.get('http://127.0.0.1:5000/health')
        print(f"Health Check - Status: {response.status_code}")
        if response.status_code == 200:
            data = response.json()
            print(f"Server Status: {data['status']}")
            print(f"Matching Method: {data['matching_method']}")
            print(f"Threshold: {data['visual_threshold']}")
            return True
        else:
            print(f"Error: {response.text}")
            return False
    except requests.exceptions.ConnectionError:
        print("Error: Could not connect to server. Is it running?")
        return False
    except Exception as e:
        print(f"Error: {e}")
        return False

def test_verify_endpoint():
    """Test the verify endpoint with a dummy request"""
    try:
        # Create a simple test image (1x1 pixel PNG)
        import base64
        test_image_data = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==')
        
        # Save test image
        with open('test_image.png', 'wb') as f:
            f.write(test_image_data)
        
        # Test the verify endpoint
        with open('test_image.png', 'rb') as f:
            files = {'fingerprint': ('test.png', f, 'image/png')}
            response = requests.post('http://127.0.0.1:5000/verify', files=files)
        
        print(f"\nVerify Endpoint Test - Status: {response.status_code}")
        if response.status_code in [200, 404]:  # 404 is expected if no students enrolled
            try:
                data = response.json()
                print(f"Response: {json.dumps(data, indent=2)}")
            except:
                print(f"Response: {response.text}")
        else:
            print(f"Error: {response.text}")
        
        # Clean up test file
        import os
        if os.path.exists('test_image.png'):
            os.remove('test_image.png')
            
    except Exception as e:
        print(f"Error testing verify endpoint: {e}")

if __name__ == "__main__":
    print("Testing BioAttend Fingerprint API...")
    print("=" * 40)
    
    # Test health endpoint
    if test_health():
        print("\n✅ Health check passed!")
        
        # Test verify endpoint
        test_verify_endpoint()
    else:
        print("\n❌ Health check failed!")
        print("Please ensure the Flask server is running on port 5000")
    
    print("\n" + "=" * 40)
    print("Test completed!")

