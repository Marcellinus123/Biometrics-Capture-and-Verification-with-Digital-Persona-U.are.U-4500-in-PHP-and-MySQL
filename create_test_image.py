from PIL import Image
import numpy as np

def create_test_image():
    """Create a valid test PNG image"""
    # Create a simple 8x8 grayscale image
    img_array = np.random.randint(0, 255, (8, 8), dtype=np.uint8)
    img = Image.fromarray(img_array, mode='L')
    
    # Save as PNG
    test_file = 'test_fingerprint.png'
    img.save(test_file, 'PNG')
    
    print(f"Created test image: {test_file}")
    print(f"Image size: {img.size}")
    print(f"Image mode: {img.mode}")
    
    return test_file

if __name__ == "__main__":
    create_test_image()

