import cv2
import numpy as np
import matplotlib.pyplot as plt
from scipy import ndimage, signal
from sklearn.metrics import pairwise_distances
from skimage.feature import hog
import tensorflow as tf
from tensorflow.keras.applications import VGG16
from tensorflow.keras.models import Model
from tensorflow.keras.preprocessing import image
from tensorflow.keras.applications.vgg16 import preprocess_input

class AdvancedFingerprintMatcher:
    def __init__(self):
        # Parameters for feature extraction
        self.minutia_radius = 12
        self.orientation_block_size = 16
        self.similarity_threshold = 0.65  # Adjusted for better accuracy
        
        # Load pre-trained model for deep features
        self.feature_model = self._load_feature_extractor()
    
    def _load_feature_extractor(self):
        """Load pre-trained VGG16 model for feature extraction"""
        base_model = VGG16(weights='imagenet', include_top=False)
        model = Model(inputs=base_model.input, outputs=base_model.get_layer('block3_pool').output)
        return model
    
    def load_and_preprocess(self, image_path, enhance=True):
        """Load and preprocess fingerprint image with enhanced techniques"""
        # Read image
        img = cv2.imread(image_path, cv2.IMREAD_GRAYSCALE)
        if img is None:
            raise ValueError(f"Could not load image: {image_path}")
        
        # Resize to standard size for consistent processing
        img = cv2.resize(img, (300, 300))
        
        if enhance:
            # Apply adaptive histogram equalization for better contrast
            clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
            img = clahe.apply(img)
            
            # Apply non-local means denoising
            img = cv2.fastNlMeansDenoising(img)
            
            # Apply Gabor filtering to enhance ridges
            kernel = cv2.getGaborKernel((21, 21), 5.0, np.pi/4, 10.0, 0.5, 0, ktype=cv2.CV_32F)
            img = cv2.filter2D(img, cv2.CV_8UC3, kernel)
        
        # Normalize image
        img = img.astype(np.float32) / 255.0
        
        return img
    
    def calculate_orientation_field(self, img, block_size=16):
        """Calculate robust orientation field using gradient information"""
        # Calculate gradients
        gx = cv2.Sobel(img, cv2.CV_64F, 1, 0, ksize=3)
        gy = cv2.Sobel(img, cv2.CV_64F, 0, 1, ksize=3)
        
        # Smooth gradients to reduce noise
        gx = cv2.GaussianBlur(gx, (5, 5), 0)
        gy = cv2.GaussianBlur(gy, (5, 5), 0)
        
        # Calculate orientation
        orientation = 0.5 * np.arctan2(gy, gx) + np.pi/2
        
        # Smooth orientation field
        orientation = cv2.GaussianBlur(orientation, (7, 7), 0)
        
        return orientation
    
    def extract_frequency_features(self, img):
        """Extract frequency domain features using Fourier Transform"""
        # Apply Fourier Transform
        f = np.fft.fft2(img)
        fshift = np.fft.fftshift(f)
        magnitude_spectrum = 20 * np.log(np.abs(fshift) + 1)  # Add 1 to avoid log(0)
        
        # Extract features from different frequency bands
        features = []
        h, w = magnitude_spectrum.shape
        center_y, center_x = h // 2, w // 2
        
        # Low frequency features (center region)
        low_freq = magnitude_spectrum[center_y-20:center_y+20, center_x-20:center_x+20]
        features.extend([np.mean(low_freq), np.std(low_freq)])
        
        # Medium frequency features
        mid_freq = magnitude_spectrum[center_y-40:center_y+40, center_x-40:center_x+40]
        mid_freq = mid_freq[20:-20, 20:-20]  # Exclude low frequencies
        features.extend([np.mean(mid_freq), np.std(mid_freq)])
        
        # High frequency features (corners)
        corners = [
            magnitude_spectrum[:30, :30],  # Top-left
            magnitude_spectrum[:30, -30:],  # Top-right
            magnitude_spectrum[-30:, :30],  # Bottom-left
            magnitude_spectrum[-30:, -30:]  # Bottom-right
        ]
        for corner in corners:
            features.extend([np.mean(corner), np.std(corner)])
        
        return np.array(features)
    
    def extract_hog_features(self, img):
        """Extract Histogram of Oriented Gradients features"""
        # Calculate HOG features
        features, hog_image = hog(
            img, orientations=8, pixels_per_cell=(16, 16),
            cells_per_block=(1, 1), visualize=True, feature_vector=True
        )
        return features
    
    def extract_deep_features(self, img):
        """Extract features using pre-trained deep learning model"""
        # Preprocess image for VGG16
        img_rgb = np.stack([img] * 3, axis=-1)  # Convert to 3-channel
        img_rgb = image.img_to_array(img_rgb)
        img_rgb = np.expand_dims(img_rgb, axis=0)
        img_rgb = preprocess_input(img_rgb)
        
        # Extract features
        features = self.feature_model.predict(img_rgb)
        return features.flatten()
    
    def align_images(self, img1, img2):
        """Align two fingerprint images using phase correlation"""
        # Calculate phase correlation
        img1_fft = np.fft.fft2(img1)
        img2_fft = np.fft.fft2(img2)
        
        # Compute cross-power spectrum
        cross_power = (img1_fft * np.conj(img2_fft)) / (np.abs(img1_fft * np.conj(img2_fft)) + 1e-10)
        
        # Compute phase correlation
        phase_correlation = np.fft.ifft2(cross_power)
        
        # Find translation
        max_pos = np.unravel_index(np.argmax(np.abs(phase_correlation)), phase_correlation.shape)
        translation = (max_pos[0] - img1.shape[0]//2, max_pos[1] - img1.shape[1]//2)
        
        # Apply translation to align images
        rows, cols = img2.shape
        M = np.float32([[1, 0, translation[1]], [0, 1, translation[0]]])
        img2_aligned = cv2.warpAffine(img2, M, (cols, rows))
        
        return img2_aligned, translation
    
    def match_fingerprints(self, img1_path, img2_path, show_results=True):
        """Main function to match two fingerprint images using multiple techniques"""
        # Preprocess both images
        img1 = self.load_and_preprocess(img1_path)
        img2 = self.load_and_preprocess(img2_path)
        
        # Try to align images for better comparison
        try:
            img2_aligned, translation = self.align_images(img1, img2)
            alignment_score = np.sqrt(translation[0]**2 + translation[1]**2)
            
            # If alignment shift is too large, use original image
            if alignment_score > 50:  # Threshold for reasonable alignment
                img2_aligned = img2
        except:
            img2_aligned = img2
        
        # Extract multiple feature types
        features1 = self.extract_multiple_features(img1)
        features2 = self.extract_multiple_features(img2_aligned)
        
        # Calculate multiple similarity scores
        similarity_scores = []
        
        # 1. Structural Similarity Index (SSIM)
        ssim_score = self.calculate_ssim(img1, img2_aligned)
        similarity_scores.append(ssim_score)
        
        # 2. Correlation coefficient
        corr_score = np.corrcoef(img1.flatten(), img2_aligned.flatten())[0, 1]
        similarity_scores.append(max(0, corr_score))  # Ensure non-negative
        
        # 3. Feature vector cosine similarity
        feature_sim = self.cosine_similarity(features1, features2)
        similarity_scores.append(feature_sim)
        
        # 4. HOG feature similarity
        hog1 = self.extract_hog_features(img1)
        hog2 = self.extract_hog_features(img2_aligned)
        hog_sim = self.cosine_similarity(hog1, hog2)
        similarity_scores.append(hog_sim)
        
        # 5. Frequency domain similarity
        freq1 = self.extract_frequency_features(img1)
        freq2 = self.extract_frequency_features(img2_aligned)
        freq_sim = self.cosine_similarity(freq1, freq2)
        similarity_scores.append(freq_sim)
        
        # Weighted average of all similarity scores
        weights = [0.25, 0.15, 0.25, 0.20, 0.15]  # Adjust based on importance
        match_score = np.average(similarity_scores, weights=weights)
        
        # Determine if fingerprints match
        is_match = match_score > self.similarity_threshold
        
        if show_results:
            self.visualize_results(
                img1_path, img2_path, 
                img1, img2_aligned,
                similarity_scores, match_score, is_match
            )
        
        return is_match, match_score, similarity_scores
    
    def extract_multiple_features(self, img):
        """Extract combined feature vector from multiple methods"""
        features = []
        
        # HOG features
        hog_features = self.extract_hog_features(img)
        features.extend(hog_features)
        
        # Frequency features
        freq_features = self.extract_frequency_features(img)
        features.extend(freq_features)
        
        # Deep features (first 128 dimensions to keep it manageable)
        deep_features = self.extract_deep_features(img)
        features.extend(deep_features[:128])
        
        return np.array(features)
    
    def calculate_ssim(self, img1, img2):
        """Calculate Structural Similarity Index"""
        # Constants
        C1 = (0.01 * 255) ** 2
        C2 = (0.03 * 255) ** 2
        
        img1 = (img1 * 255).astype(np.uint8)
        img2 = (img2 * 255).astype(np.uint8)
        
        kernel = cv2.getGaussianKernel(11, 1.5)
        window = np.outer(kernel, kernel.transpose())
        
        mu1 = cv2.filter2D(img1, -1, window)[5:-5, 5:-5]
        mu2 = cv2.filter2D(img2, -1, window)[5:-5, 5:-5]
        
        mu1_sq = mu1 ** 2
        mu2_sq = mu2 ** 2
        mu1_mu2 = mu1 * mu2
        
        sigma1_sq = cv2.filter2D(img1 ** 2, -1, window)[5:-5, 5:-5] - mu1_sq
        sigma2_sq = cv2.filter2D(img2 ** 2, -1, window)[5:-5, 5:-5] - mu2_sq
        sigma12 = cv2.filter2D(img1 * img2, -1, window)[5:-5, 5:-5] - mu1_mu2
        
        ssim_map = ((2 * mu1_mu2 + C1) * (2 * sigma12 + C2)) / ((mu1_sq + mu2_sq + C1) * (sigma1_sq + sigma2_sq + C2))
        return np.mean(ssim_map)
    
    def cosine_similarity(self, vec1, vec2):
        """Calculate cosine similarity between two vectors"""
        dot_product = np.dot(vec1, vec2)
        norm1 = np.linalg.norm(vec1)
        norm2 = np.linalg.norm(vec2)
        return dot_product / (norm1 * norm2 + 1e-10)  # Avoid division by zero
    
    def visualize_results(self, img1_path, img2_path, img1, img2, scores, match_score, is_match):
        """Visualize the matching results with detailed information"""
        # Load original images for display
        orig_img1 = cv2.imread(img1_path)
        orig_img2 = cv2.imread(img2_path)
        
        if orig_img1 is not None and orig_img2 is not None:
            # Resize for consistent display
            orig_img1 = cv2.resize(orig_img1, (300, 300))
            orig_img2 = cv2.resize(orig_img2, (300, 300))
            
            # Create side-by-side comparison
            height = max(orig_img1.shape[0], orig_img2.shape[0])
            width = orig_img1.shape[1] + orig_img2.shape[1]
            
            comparison = np.zeros((height, width, 3), dtype=np.uint8)
            comparison[0:orig_img1.shape[0], 0:orig_img1.shape[1]] = orig_img1
            comparison[0:orig_img2.shape[0], orig_img1.shape[1]:orig_img1.shape[1] + orig_img2.shape[1]] = orig_img2
            
            # Add text with result
            result_text = f"Match: {is_match} (Score: {match_score:.3f})"
            color = (0, 255, 0) if is_match else (0, 0, 255)
            cv2.putText(comparison, result_text, (10, 30), 
                       cv2.FONT_HERSHEY_SIMPLEX, 0.8, color, 2)
            
            # Add individual similarity scores
            score_text = [
                f"SSIM: {scores[0]:.3f}",
                f"Correlation: {scores[1]:.3f}",
                f"Feature Similarity: {scores[2]:.3f}",
                f"HOG Similarity: {scores[3]:.3f}",
                f"Frequency Similarity: {scores[4]:.3f}"
            ]
            
            for i, text in enumerate(score_text):
                y_pos = 60 + i * 25
                cv2.putText(comparison, text, (10, y_pos), 
                           cv2.FONT_HERSHEY_SIMPLEX, 0.5, (255, 255, 255), 1)
            
            # Display result
            plt.figure(figsize=(15, 8))
            plt.imshow(cv2.cvtColor(comparison, cv2.COLOR_BGR2RGB))
            plt.title("Fingerprint Matching Result")
            plt.axis('off')
            plt.tight_layout()
            plt.show()
            
            # Also show enhanced images for comparison
            fig, axes = plt.subplots(2, 2, figsize=(10, 8))
            axes[0, 0].imshow(orig_img1)
            axes[0, 0].set_title('Original Fingerprint 1')
            axes[0, 0].axis('off')
            
            axes[0, 1].imshow(orig_img2)
            axes[0, 1].set_title('Original Fingerprint 2')
            axes[0, 1].axis('off')
            
            axes[1, 0].imshow(img1, cmap='gray')
            axes[1, 0].set_title('Enhanced Fingerprint 1')
            axes[1, 0].axis('off')
            
            axes[1, 1].imshow(img2, cmap='gray')
            axes[1, 1].set_title('Enhanced/Aligned Fingerprint 2')
            axes[1, 1].axis('off')
            
            plt.tight_layout()
            plt.show()

# Example usage
if __name__ == "__main__":
    matcher = AdvancedFingerprintMatcher()
    
    # Replace these paths with your fingerprint images
    image1_path = "8.png"
    image2_path = "10.png"
    
    try:
        is_match, score, detailed_scores = matcher.match_fingerprints(image1_path, image2_path)
        print(f"Fingerprints match: {is_match}")
        print(f"Overall similarity score: {score:.3f}")
        print("Detailed scores:")
        print(f"  SSIM: {detailed_scores[0]:.3f}")
        print(f"  Correlation: {detailed_scores[1]:.3f}")
        print(f"  Feature Similarity: {detailed_scores[2]:.3f}")
        print(f"  HOG Similarity: {detailed_scores[3]:.3f}")
        print(f"  Frequency Similarity: {detailed_scores[4]:.3f}")
    except Exception as e:
        print(f"Error: {e}")
        import traceback
        traceback.print_exc()
        print("Please make sure the image paths are correct and the images are valid fingerprint images.")