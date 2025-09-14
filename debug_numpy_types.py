import cv2
import numpy as np
import json

def test_numpy_serialization():
    """Test where numpy types are causing JSON serialization issues"""
    
    # Create a test image
    test_img = np.random.randint(0, 255, (64, 64), dtype=np.uint8)
    
    print("Testing numpy type serialization...")
    
    # Test 1: Basic template matching
    print("\n1. Testing cv2.matchTemplate...")
    try:
        result = cv2.matchTemplate(test_img, test_img, cv2.TM_CCOEFF_NORMED)
        score = np.max(result)
        print(f"   Score type: {type(score)}")
        print(f"   Score value: {score}")
        
        # Try to serialize
        test_data = {'score': score}
        json.dumps(test_data)
        print("   ✅ JSON serialization successful")
    except Exception as e:
        print(f"   ❌ JSON serialization failed: {e}")
    
    # Test 2: Histogram comparison
    print("\n2. Testing histogram comparison...")
    try:
        hist1 = cv2.calcHist([test_img], [0], None, [256], [0, 256])
        hist2 = cv2.calcHist([test_img], [0], None, [256], [0, 256])
        hist_similarity = cv2.compareHist(hist1, hist2, cv2.HISTCMP_CORREL)
        print(f"   Histogram similarity type: {type(hist_similarity)}")
        print(f"   Histogram similarity value: {hist_similarity}")
        
        # Try to serialize
        test_data = {'hist_similarity': hist_similarity}
        json.dumps(test_data)
        print("   ✅ JSON serialization successful")
    except Exception as e:
        print(f"   ❌ JSON serialization failed: {e}")
    
    # Test 3: Normalized image
    print("\n3. Testing normalized image...")
    try:
        normalized = cv2.normalize(test_img, None, 0, 1, cv2.NORM_MINMAX, cv2.CV_32F)
        mean_val = np.mean(normalized)
        print(f"   Mean value type: {type(mean_val)}")
        print(f"   Mean value: {mean_val}")
        
        # Try to serialize
        test_data = {'mean_val': mean_val}
        json.dumps(test_data)
        print("   ✅ JSON serialization successful")
    except Exception as e:
        print(f"   ❌ JSON serialization failed: {e}")
    
    # Test 4: Combined data
    print("\n4. Testing combined data...")
    try:
        combined_data = {
            'template_score': float(np.max(cv2.matchTemplate(test_img, test_img, cv2.TM_CCOEFF_NORMED))),
            'hist_score': float(cv2.compareHist(
                cv2.calcHist([test_img], [0], None, [256], [0, 256]),
                cv2.calcHist([test_img], [0], None, [256], [0, 256]),
                cv2.HISTCMP_CORREL
            )),
            'mean_score': float(np.mean(cv2.normalize(test_img, None, 0, 1, cv2.NORM_MINMAX, cv2.CV_32F)))
        }
        
        print(f"   Combined data: {combined_data}")
        json.dumps(combined_data)
        print("   ✅ JSON serialization successful")
    except Exception as e:
        print(f"   ❌ JSON serialization failed: {e}")

if __name__ == "__main__":
    test_numpy_serialization()

