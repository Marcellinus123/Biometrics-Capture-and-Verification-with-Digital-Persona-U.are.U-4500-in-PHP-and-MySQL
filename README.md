# Biometrics-Capture-and-Verification-with-Digital-Persona-U.are.U-4500-in-PHP-and-MySQL  

A **PHP + MySQL** project for capturing and verifying fingerprints with the **Digital Persona U.are.U 4500 scanner**.  
This system features secure enrollment, template storage, and verification for authentication systems such as **student bio-data registration, attendance, exams, banking, and access control**.  

---

## 🔑 Admin Login  

![Screenshot of the Login Interface.](/ss/3.png)  
The login interface for system administrators. This part runs on an Apache server (XAMPP with PHP + MySQLi).  

![Admin Dashboard Interface.](/ss/4.png)  
The admin dashboard where all operations begin.  

---

## 🏫 Managing Academic Data  

![Manage Programme Interface.](/ss/5.png)  
Admins can add, edit, update, and delete programs.  

![Manage Courses Interface.](/ss/9.png)  
Admins can manage courses, including creating, editing, and updating records.  

![Manage Lecturers Interface.](/ss/11.png)  
Admins add lecturer records and assign courses.  

![Manage Students Interface.](/ss/14.png)  
The student management module. Admins add or update student records here.  
👉 **Note:** Fingerprint enrollment happens only after the student is registered.  

![Students List Interface.](/ss/16.png)  

---

## 🖐️ Fingerprint Enrollment  

This section is the backbone of the system.  

The administrator connects the **Digital Persona U.are.U 4500 fingerprint scanner** via USB. The manufacturer’s Web SDK allows fingerprint **capture** but does not provide **verification**. After installing the SDK, the admin can enroll fingerprints directly into the system.  

The process:  
1. Select a student from the system.  
2. Place the student’s finger on the scanner.  
3. Capture and save the fingerprint template.  
4. The template is stored securely in the database as a PNG.  

If the scanner is not connected, the system throws an error.  

![Fingerprint enrollment interface.](/ss/bio1.png)  
No scanner connected.  

![Fingerprint enrollment interface.](/ss/bio2.png)  
Scanner successfully detected.  

![Fingerprint enrollment interface.](/ss/bio3.png)  
Prompt to place a finger on the scanner.  

![Fingerprint enrollment interface.](/ss/bio4.png)  
Fingerprint captured.  

![Fingerprint enrollment interface.](/ss/bio5.png)  
Captured template saved to the database.  

---

## 🔍 Fingerprint Verification  

The **main challenge** was integrating fingerprint verification inside the browser.  
Since the original Web SDK from Digital Persona does **not provide verification or matching**, we implemented our own algorithm in **Python**, served via a **Flask API**.  

The PHP application captures a fingerprint, sends it to the Flask server, and waits for a verification response. If a match is found, the server queries the MySQL database and returns the associated student data.  

### Python Fingerprint Matching  

The file `finger_clean.py` handles all fingerprint matching logic.  
It uses:  
- **OpenCV (cv2)** for image preprocessing.  
- **NumPy** for efficient matrix operations.  
- **scikit-learn’s cosine similarity** for fingerprint comparison.  
- **Flask** to serve the verification API.  

![Fingerprint verification logic.](/ss/Screenshot 2025-08-25 215749.png)  

---

## 🌐 System Architecture  

This project requires **two servers** running simultaneously:  
1. **XAMPP/Apache** → Handles PHP + MySQL.  
2. **Python Flask API** → Handles fingerprint verification (`finger_clean.py`).  

![Fingerprint verification servers.](/ss/server.png)  
![Fingerprint verification servers.](/ss/server2.png)  

---

## 🔄 Verification Workflow  

1. A student places their finger on the scanner.  
2. The system captures a new fingerprint template.  
3. The captured fingerprint is sent via API request to the Flask server (running on port 5000).  
4. The Flask server compares the fingerprint against stored templates in MySQL.  
5. If a match is found, the server returns the associated student record.  

![Fingerprint verification Interface.](/ss/veri2.png)  
Captured fingerprint ready for verification.  

![Fingerprint verification Interface.](/ss/veri3.png)  
Successful match response from the Flask server.  

---

## 📖 Project Description  

This system was developed to provide a reliable solution for capturing and verifying student biometric data using the Digital Persona U.are.U 4500 fingerprint scanner. Its primary purpose is **accurate bio-data registration and verification of students each semester**, ensuring that identity checks during **attendance, examinations, and other academic processes** are free from impersonation.  

The platform is built with **PHP and MySQL**, providing an admin dashboard where administrators can securely log in and manage student records. Once student records are created, administrators move to the enrollment phase, where fingerprint data is captured directly from the scanner and stored in the database.  

During verification, students simply place their finger on the scanner, and the system compares the captured template with the stored record. If a match is found, the student’s identity is confirmed. This workflow is particularly critical during examinations and semester registrations.  

Since the U.are.U 4500 scanner lacks a built-in verification endpoint, we developed a **custom fingerprint verification system in Python with Flask**. This Python service leverages **OpenCV, NumPy, and cosine similarity** to compare fingerprints and ensure accurate matches.  

By combining **PHP (data management)**, **MySQL (storage)**, and **Python Flask (verification)**, this project delivers a **complete end-to-end biometric solution**. While originally designed for student bio-data management, it can also be extended to **employee attendance systems, building access control, and financial services authentication**.  

This project showcases how **cross-technology integration** can bridge gaps left by hardware manufacturers and deliver a **scalable, secure, and reliable biometric verification system**.  

---
