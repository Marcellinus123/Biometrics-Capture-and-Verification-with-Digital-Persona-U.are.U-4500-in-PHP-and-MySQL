-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 25, 2025 at 09:22 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bioattend_db_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `username`, `password`, `full_name`, `email`, `created_at`) VALUES
(1, 'admin1', '$2y$10$.pkCOeD6q3MiN66HeBtMa.h4Mhg09k2BpFPUl9/ti8AphJfghMn46', 'John Doe', 'admin1@university.edu', '2025-08-02 21:06:16'),
(2, 'admin2', '$2a$10$N9qo8uLOickgx2ZMRZoMy.Mrq4H7pB7eTj7UJD8XUz7Vl2Q9JQ1W', 'Jane Smith', 'admin2@university.edu', '2025-08-02 21:06:16');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `lecturer_id` int(11) DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('Present','Absent') NOT NULL,
  `marked_by` enum('Lecturer','Admin') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_sessions`
--

CREATE TABLE `attendance_sessions` (
  `session_id` int(11) NOT NULL,
  `session_name` varchar(255) NOT NULL,
  `course_code` varchar(50) DEFAULT NULL,
  `course_name` varchar(255) DEFAULT NULL,
  `instructor_id` int(11) DEFAULT NULL,
  `session_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL,
  `course_code` varchar(20) NOT NULL,
  `course_name` varchar(100) NOT NULL,
  `credit_hours` int(11) NOT NULL,
  `program_id` int(11) DEFAULT NULL,
  `level` int(11) NOT NULL,
  `semester` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`course_id`, `course_code`, `course_name`, `credit_hours`, `program_id`, `level`, `semester`) VALUES
(1, 'CS101', 'Computer basics', 3, 3, 1, 1),
(2, 'DML101', 'Introduction to Medi Lab', 3, 4, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `lecturers`
--

CREATE TABLE `lecturers` (
  `lecturer_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `lecturers`
--

INSERT INTO `lecturers` (`lecturer_id`, `username`, `password`, `full_name`, `email`, `created_at`) VALUES
(1, 'lecturer1', '$2a$10$N9qo8uLOickgx2ZMRZoMy.Mrq4H7pB7eTj7UJD8XUz7Vl2Q9JQ1W', 'Professor Williams', 'williams@university.edu', '2025-08-02 21:06:16'),
(2, 'lecturer2', '$2a$10$N9qo8uLOickgx2ZMRZoMy.Mrq4H7pB7eTj7UJD8XUz7Vl2Q9JQ1W', 'Dr. Johnson', 'johnson@university.edu', '2025-08-02 21:06:16');

-- --------------------------------------------------------

--
-- Table structure for table `lecturer_courses`
--

CREATE TABLE `lecturer_courses` (
  `assignment_id` int(11) NOT NULL,
  `lecturer_id` int(11) DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `academic_year` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `lecturer_courses`
--

INSERT INTO `lecturer_courses` (`assignment_id`, `lecturer_id`, `course_id`, `academic_year`) VALUES
(1, 2, 1, '2025/2026');

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `program_id` int(11) NOT NULL,
  `program_name` varchar(100) NOT NULL,
  `program_code` varchar(20) NOT NULL,
  `duration_years` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `programs`
--

INSERT INTO `programs` (`program_id`, `program_name`, `program_code`, `duration_years`) VALUES
(3, 'Diploma in Computer Science', 'DCS', 2),
(4, 'Diploma in Medical Laborary Technology', 'MTT', 2),
(6, 'Cyber Security', 'CBS', 2);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `student_number` varchar(15) NOT NULL,
  `registration_number` varchar(20) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `program_id` int(11) DEFAULT NULL,
  `level` int(11) NOT NULL,
  `fingerprint_data` text DEFAULT NULL,
  `passport_photo` text DEFAULT NULL,
  `reported_status` enum('Reported','Not Reported') DEFAULT 'Not Reported',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `student_number`, `registration_number`, `full_name`, `email`, `program_id`, `level`, `fingerprint_data`, `passport_photo`, `reported_status`, `is_active`, `created_at`, `updated_at`) VALUES
(4, '20230204008', '77I02GTV2025', 'Tamimi', 'tamo@gmail.com', 3, 200, 'students_fingerprint/4.png', '', 'Not Reported', 1, '2025-08-19 18:07:11', NULL),
(5, '20230204013', '6KWUAQCM2025', 'Huseini Mubarakatu', 'mubaratuhuseini@gmail.com', 3, 200, 'students_fingerprint/5.png', NULL, 'Not Reported', 1, '2025-08-19 18:07:11', NULL),
(6, '20230204017', 'YUQB8BPT2025', 'Dongyiri Arnold Desmond Banabale', 'dongyiriarnold@gmail.com', 3, 200, '', NULL, 'Not Reported', 1, '2025-08-19 18:07:11', NULL),
(7, '20230204010', '8H1C9TKH2025', 'kanton Gad Beveru', 'kantongad9@gmail.com', 3, 200, '', NULL, 'Not Reported', 1, '2025-08-19 18:07:11', NULL),
(8, '20230212013', '9FFT286E2025', 'Maxwell Ayenera', 'maxwellayenear@gmail.com', 3, 200, '', NULL, 'Not Reported', 1, '2025-08-19 18:07:11', NULL),
(9, '20230204015', '7VCXKWZS2025', 'Theodora Nelson Cpfie', 'tmnelsoncofie@gmail.com', 3, 200, 'students_fingerprint/9.png', NULL, 'Not Reported', 1, '2025-08-19 18:07:11', NULL),
(10, '20230204005', 'TZBSKO7K2025', 'Marcellinus Atampugre', 'baba@mail.com', 3, 200, 'students_fingerprint/10.png', '', 'Not Reported', 1, '2025-08-19 18:07:11', NULL),
(11, 'TEST001', 'REG001', 'Test Student', '', NULL, 0, 'iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAAAAACPAi4CAAAPMElEQVR4nAXBSVQbB2MAYM1oRqORNNJo39CCQAIJsQizY8AGjO1gO3acOEnj19e/lx76Xk/tsff/9dRDr21f//yJszj+vSUGYzCrWIRZJSEQWpDQhvZ9mRmp3wf8q5qvS+1aEpfDoO8IJ2s9npEtsXe4cniP+nFoG5vwV8nhpNv77erV0ySVORua10VQnHk2a73SlhhFSQMcoDUfpsQcCRMlsX5I/PnOOKFLMuc01OYnpenW8La5THd/6n9Itih/i9jZg8uAP0OW1FUC+JwYHe+txkHInZcJE79lz39lV9Z7BD+P/VQkzNL2i05vH/V8HmXsVAApd9MLB2vXMz74DfPBw4l+HfE0j4X8R7Q1dTvwnybMYVjkDQf26dPviwJCrVzW+bNN+OGUT9ae4D9jSs+l/dyjULHp4nosJNHEQKTi6fdP7oFJzMNOm4H/8Esz1yu7g8I3Ew1WRGO3f3WJ5hlsUhhibHO0B2hXBlVX9i01N87wToL2KnxhCZcq1UaznoHO557YIfQp9SYFCcXn3avazmagI9fgr3ErhZnTarSRGFVXs439XASRntVQTBvxJ/SBMZH+Xb8qGAxGJzW7QS3EWVZKhUvVYb6Kab18aKVpqTl1o0exGaXuFxd3RSqg2tdOBuKlsmtG+bq/aIhRL+7f2QroBjPNgHP2hAuse07vPb8bXuMJuaXegjSTOodxTXyrx8tShxkdtDNtNRQqTBevjMtjFRBxJB76cr7yaIbKT9v5yv/m1oF/N8oWuk6NcUxUQ2L1QAIVJvmVDuFybWB+7HLEqbrQ08DnsrHsQpdpidYwhwDwogyaS4w41bfajbsLYCt0DuLy1ap6q8aO+usD+WRrtxx/0TsYxneksdwGzVa9+LYNWB/Do1cjw1xlSIQ/1dcrvGGLhwpeNGVBe8HVWqypCw75yQqkuFFsKnYE2jkcJGaU8YrctCIPL+55lDE+h79/j7aBxYrHhu9BT4V+HJM+nga5MPDyiuOSsGrZrA7SHXByXfVNBXw25irF5PG7Ly2UsoJYTZDcaknlTgZJtetIC6B1GBbM1bHbkKNCmYEN58GwrlDIewYu9ycF8Qvc3VZkaSv5Bolw84pwTsqoJ+3Zm1GxnZ7nmCIt6Bo1ZuvemSwi5Y0vqqx5sBhsrP6QdQssDdcM79f4YLUKdHG3OZC10LSFv8pfZI7nGeA3W8DOGFrOwqbKC7kgDO0qV9Zfeu+9g6ibwD+ZOuINLh1/JXQ3X3z1sUH05pMtR7VrLjnvFCj2nF7irWUlx9H5noT0Af3GP77ulgWwct2hN8wRsqquAT5kxBhxZHtuJDIuvL3SXKO5nKK17iYgLa92Ckad4T/VlLIsXGA+JmsKUDjjFy29qpRdx+34L4WO+AANhOpBJHzjCu/7Y8aPLFfbtMlb75Ofv1WtWY4KtRJAEy7xo4IzVfoZa4C+C2PvJeZTSU6BsxUv287Iez935IE/Fztkf9TMUv55zvIDqPHOEqI5AlE2TC86DiaVv8oDXx0eG0wsj4RJEjsEoQg/utT8cGt5Clxn1JB6XAD8sDyzxGZ35POxu9sh8usTSpxKj/90M5kG8xhUA/r/YkZ09XfiwbniLTtQNEKZtjdm9XoXdFJLSEyZahC0f6uEkImg8GrEyVUyk3tmag9Z6IGoLnxI6xxiH/4D0QBTYyN/675ujSBGkYO4nHXBot1P/b2d0LrPNAo2oWUzvyEPEdGgU9BOv/sORNkC8R/swAk6P7jnzZT0gQ047h+WxE3jAeanWcrqm00zmjThyImFds8rADY924OqJZ0bmb5EistfuKWu4f1GvMJiZfTicjo59rY0rt30DqkAu7PFaAVYasnbmwdTz5s6mOAuCKRuguukGIuXrZn+ixShghfUV4wlRvOYpk6TxyqtbZ0VaZ/TU+DB4AJfRu7xTf0+Hz9sSBBG/nxa402jn4AVkumtlDt/nj6+vSTnA1n+Rv8Z1r3XlsljvoxsIBGoGuYJzFDqSxBXoX6/zJOXjsSOB2kHoJTWWvrFDH1Ucmn8s7Q4OAZIz7FqKzQRSRbwHmuMkuSINvc+lkT+PpfaUZQWnqw0FcP9x+Z48cPQ4ufMuDSy2Q/XoIxwnwkIWmXuFeKBEIl7T6HbAG51qSTlVLObsN7OTdsWxmOD/FV1BZFpTp9ZiqP/O+v7PMilFHYhKBkCPmRpuU1tmqjBll0lzLlQGuivyFk6Z2skzX6Lt1kfR7mMQ24wLgEb5T5/1/OpxS5eSXV0qdW971BDUS3wz9gIyHhvqdTBs17u7z0gyRT4nbeFrz477vw0sthfTdR8zGaZd5+lKDRJsJ3ebJAHwnq7iD9nru13KRLAs5Pyo72GDHQyMSdxM4eVVVvhvpgxHKhyShmENesUeZLVpvTwEiFUpZqSFY5qoz3ZQ70zmIvkG9aYAPje3ixK0bUh8vBaCRfH9gyx3Be2MGmstUBehae7vspXLyjVpL3vuKVMK7FqOYKcet13Nm4t5Nl3D/IYSLBw3M0kSQaqBz8dH3FMyWzoUldvAe1eJZaau+BURfqB5ZOGUhXrsXB6Sp26rt+UUWWQKN5q+PrrEOgPV3eZwdcI21y+Gmgh9faihNRHHiFF+Z4jpTNiTkOGLN/uy2zUVRlrcfVaYzNtFGd62u+iyWFlpKcJWF7IThQ2FGwPRw+JP3wFLjRQgShwyvgiojlLuCE61r9IjTkmnLD3sVO10sOlXG5dT6B2cD9+BFSIcTG0U+gmD0W1awV1+0vgy8SakIC7U2asZb/Px5PE0RvBwHcEDPtcs612jhsBSJsWk9dQU2E3otDgIl8OQp9s0KWW2LwiXVdVVmYeuE0xL2C/5++OtyTnp5i2FvyTpBZUa0q7zarV1s3p2O1jbtgZGsvQ7KcgUipClb/yxzgv9a3DtURNpQksoqbEuakQTYZo8bDKcXknAiH4mYd5rR4ePaSV7p4+fWnZUt0q2JsJ4SnWttsFFpkt5PLXbNXcBedaoNAkfuRPKxiTK3XkSky5J9A5r9bA1ozzHK9aGMcdN0uCv+h1k1xi2xFblwoLuSYKvIELNuA3rI1i0JuWnIb5DHwMPf7DyLtiraJPrsbxATB78Y7eLtcrAkoOcCKHtI7kmlg1wbhaHhrDaAXg32ZLRy1LtOvEwcS7adVc97yKnZUJJeCmQPtGq69T/EvFSRWQlcFPFZNaAL3AzSzOobByNPVhlG0rSWPAfwltelMV3tVKnmuA4LWT1gIUuXvuGGG+gCWprzYH17VeIVf2vFMJnCcJiEJCPF1RdEhXxXKcmipmlEE+P1hfxpBExqDqhV0CgJ0YOnzFkZO/Yrdocwc+A7w2vojr0ebnN9JSQWN1pIOJX51Agys3vfpYmqy8B9bqF2Do/PEeT5oiIkrAkzaKtoesI7XyJTxsS02t8OGszjageG3IGs46D6Ze398nue4Hf+s/LRQe1FleD7hMHMlkSgb/ICxul0Ax5uMi5zbjG9ipkdAL0p5SrthpMClxDlAyWjlOw/NHhzoEgBmDVKy5lUfPyIdA07tSDFPT9misOmywW8zLJarK/Zg1nnuE4oOSeObGpj0aTTyzKA6hiorb2KosCKOACyEGLpBtuHEQBTMP2mUf0UDjO8/uMR1h8y3dG+gHGtkWnlE3ZFf1BYwa54Kap8aEHAwjFWWuVc0z0Uo7WdtAlOmiUAps8YQ/jkZPRFm2SWKb3aCATV6UfU20TROth3g3nqN/Fdgg1e7HH8d4ymnpRrFzicpwRL5m7qM0FqbZdVHgzwoYsKES+4NL6gzJTkBH9CLeaTPbE8PEGT+lthDwS33buV1eMUESZyFVv+HoWOHU299BM866Qf2hHTyXcdOt1zHDQgWkpR6vLE4NVhJvFHC39MBJlbWWFfruVHDPV2mYhGvZdsdgB5pC+Za21GP1Zi5VvOiTgzLSTouErWpht2NKZcO7T5Gpux0umhf8jDcjuvi/6yS+2HQu1FSl0dHwD5IY7ui94rJ8oN2CkJ/VhfUSaD6QhHAtjkFLzeVcuHGQOGl4im0JfvfZCKKTKD27zZjiEa1Pm7NhLSJsKxQRV+A3rlB4oW8sXP0IHIAfboazyIYup4aT22o9i8M20jCJ3GDnn21bQ6fTl+e7JPlLhy//snT6elRyJ1ateqylO30DwAG1IeVUBRAjZhTH+ZJTQ3YiTUWaKTHzJ1WW3PjGw4ASV0ADRxuT4bYdQiPnSXWZvAvvoWVah8r70evYPmvty30nRIWq3L7DolxcWZmI5s5ZEoeCYJDmX2jX0N491XIjcf8jA5ZwanX1pRyVXzTsaOd2a6xML4u9d0Xxpho4HM4CJ5dbVXt3d+mo3sJytkxRN7xiBHELVigdMsEFGX5NYAfs4NZZ9jgT7e04EVeOpNO1CCM4l4mWwVQylbR/ca0QyAInBiBvUGwmdTtGIKNsfqXyEyMLfc/IO+IZixzZsaSCtZmwCMj1iqfcoRP2YNuUo94LJoavjbVBl+WyRNxglK/oVqW7BvM+PtHLqcu+H8l0THB97uc1zJ/WbzC/c8LS+UNwve4dZw77oTeosXwB5fLViHytpcLhVnkGnj9Ld/fJadaHnpqCyJX6lmcKrOQUY9t5AUGNK+JPrwq3PRMrdTxfxqJRY9Q/SwD/Ag6Xbf02Gb2qAzbvJ9H9OydOFGkPdVk7PYZTEVQyVhf5LDbPPvHTBJ3F97NXJvf5KL5H6JOlKRu9CHwPbzcEfOOGBbzSUwlwnqbgdF6G0NJE9PDGggCFNcwjlTLmkugCp/1xnbXQRineDQsS3KybdotGIUkQ2y7qocJmyv2Slwqt8yfbegPHZZKlq7i1mdsTETOw3eP6n1AwkN/FRfUMXxiq49pMjYnHo/qTTPgdD3gLbVkE5WMTN/d7Lw2yffYJlRRN83SsSQB/pPp30UHw16erE9Qvt3yGMHPtJgTVqjuze4PpQ1lQforey4KcF1LyIKclbAfsGBqcDuFJmjOlIdVO69H1Uv66bNOF1Q3lLSjjimF7X6+vVTeOpnnmV5/GEqNt9b4MG2Tg4aLZ8f49gk/yfV0CoeLe8cAVOFO5y2f5K1SwNejgzCmhplbbtwx/U+DvGFtP6GGrg0fQ1KXFL7m/14HVrWw/HAKBkmWjOa09yBRLiM4168dMqdX0sFOcERThroPxzBuBOYRWwpLOlToNvrVeKnEAlehIFyP+H89u3C5qHJDEAAAAAElFTkSuQmCC', NULL, 'Not Reported', 1, '2025-08-23 21:51:14', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student_courses`
--

CREATE TABLE `student_courses` (
  `enrollment_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `academic_year` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `student_courses`
--

INSERT INTO `student_courses` (`enrollment_id`, `student_id`, `course_id`, `academic_year`) VALUES
(3, 4, 1, '2025/2026'),
(4, 5, 1, '2025/2026'),
(8, 6, 1, '2025/2026'),
(9, 7, 1, '2025/2026'),
(11, 8, 1, '2025/2026'),
(12, 9, 1, '2025/2026'),
(13, 10, 1, '2025/2026'),
(14, 10, 2, '2025/2026');

-- --------------------------------------------------------

--
-- Table structure for table `verification_logs`
--

CREATE TABLE `verification_logs` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `success` tinyint(1) NOT NULL,
  `confidence` decimal(5,4) DEFAULT NULL,
  `matches` varchar(255) NOT NULL,
  `visual_similarity` varchar(12) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `verification_logs`
--

INSERT INTO `verification_logs` (`id`, `student_id`, `success`, `confidence`, `matches`, `visual_similarity`, `ip_address`, `timestamp`) VALUES
(1, NULL, 0, 0.0000, '', '', '127.0.0.1', '2025-08-19 18:07:38'),
(2, NULL, 0, 0.0000, '', '', '127.0.0.1', '2025-08-19 18:07:47'),
(3, 7, 1, 1.0000, '', '', '127.0.0.1', '2025-08-19 18:15:24'),
(4, NULL, 0, 0.0000, '', '', '127.0.0.1', '2025-08-19 18:23:05'),
(5, NULL, 0, 0.0000, '', '', '127.0.0.1', '2025-08-19 18:23:40'),
(6, NULL, 0, 0.0000, '', '', '127.0.0.1', '2025-08-19 18:24:55'),
(7, NULL, 0, 0.0000, '', '', '127.0.0.1', '2025-08-19 18:25:27'),
(8, NULL, 0, 0.0000, '', '', '127.0.0.1', '2025-08-19 18:25:48'),
(9, NULL, 0, 0.0000, '', '', '127.0.0.1', '2025-08-19 18:26:33'),
(10, NULL, 0, 0.0000, '', '', '127.0.0.1', '2025-08-19 19:06:01'),
(11, 7, 1, 1.0000, '', '', '127.0.0.1', '2025-08-19 19:07:32'),
(12, NULL, 0, 0.0000, '', '', '127.0.0.1', '2025-08-19 19:38:35'),
(13, NULL, 0, 0.0000, '', '', '127.0.0.1', '2025-08-19 19:38:45'),
(14, NULL, 0, 0.0000, '', '', '127.0.0.1', '2025-08-19 19:38:58'),
(15, NULL, 0, 0.0000, '', '', '127.0.0.1', '2025-08-19 20:04:01'),
(16, NULL, 0, 0.0000, '', '', '127.0.0.1', '2025-08-19 20:05:44'),
(17, NULL, 0, 0.0000, '', '', '127.0.0.1', '2025-08-19 20:21:36'),
(18, NULL, 0, 0.0000, '', '', '127.0.0.1', '2025-08-19 20:23:12'),
(19, NULL, 0, 0.0000, '', '', '127.0.0.1', '2025-08-19 20:24:36'),
(20, NULL, 0, 0.0000, '', '', '127.0.0.1', '2025-08-19 20:27:20'),
(21, NULL, 0, 0.0000, '', '', '127.0.0.1', '2025-08-19 20:27:23'),
(22, NULL, 0, 0.0000, '', '', '127.0.0.1', '2025-08-19 20:27:25'),
(23, NULL, 0, 0.0000, '', '', '127.0.0.1', '2025-08-19 20:29:49'),
(24, NULL, 0, 0.0000, '', '', '127.0.0.1', '2025-08-19 20:30:21'),
(25, NULL, 0, 0.0000, '', '', '127.0.0.1', '2025-08-19 20:30:39'),
(26, NULL, 0, 0.0000, '', '', '127.0.0.1', '2025-08-19 20:30:41'),
(27, NULL, 0, 0.0000, '', '', '127.0.0.1', '2025-08-19 20:30:58'),
(28, NULL, 0, 0.0000, '', '', '127.0.0.1', '2025-08-19 20:31:49'),
(29, NULL, 0, 0.0000, '', '', '127.0.0.1', '2025-08-19 23:13:53'),
(30, NULL, 0, 0.0000, '0', '', '127.0.0.1', '2025-08-20 00:06:52'),
(31, 9, 1, 0.2113, '317', '', '127.0.0.1', '2025-08-20 00:09:26'),
(32, 9, 1, 0.2033, '305', '', '127.0.0.1', '2025-08-20 00:09:56'),
(33, 9, 1, 0.2127, '319', '', '127.0.0.1', '2025-08-20 00:10:18'),
(34, 9, 1, 0.2067, '310', '', '127.0.0.1', '2025-08-20 00:16:37');

-- --------------------------------------------------------

--
-- Table structure for table `verification_logs_new`
--

CREATE TABLE `verification_logs_new` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `success` tinyint(1) DEFAULT NULL,
  `confidence` decimal(5,4) DEFAULT NULL,
  `visual_similarity` decimal(5,4) DEFAULT 0.0000,
  `match_strength` varchar(20) DEFAULT 'Unknown',
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `verification_logs_new`
--

INSERT INTO `verification_logs_new` (`id`, `student_id`, `success`, `confidence`, `visual_similarity`, `match_strength`, `ip_address`, `timestamp`) VALUES
(1, NULL, 0, 0.0000, 0.0000, 'No Match', '127.0.0.1', '2025-08-20 00:56:24'),
(2, NULL, 0, 0.0000, 0.0000, 'No Match', '127.0.0.1', '2025-08-20 01:00:13'),
(3, 8, 1, 0.9500, 0.9500, 'Strong', '::1', '2025-08-20 07:05:53'),
(4, 8, 1, 0.9500, 0.9500, 'Strong', '::1', '2025-08-20 07:07:18'),
(5, NULL, 0, 0.0000, 0.0000, 'No Match', '::1', '2025-08-20 07:07:49'),
(6, 5, 1, 0.9500, 0.9500, 'Strong', '::1', '2025-08-20 07:08:15'),
(7, 8, 1, 0.9500, 0.9500, 'Strong', '::1', '2025-08-20 07:08:41'),
(8, NULL, 0, 0.0000, 0.0000, 'No Match', '127.0.0.1', '2025-08-20 10:35:57'),
(9, NULL, 0, 0.0000, 0.0000, 'No Match', '127.0.0.1', '2025-08-20 10:37:31'),
(10, NULL, 0, 0.0000, 0.0000, 'No Match', '127.0.0.1', '2025-08-20 10:42:34'),
(11, NULL, 0, 0.0000, 0.0000, 'No Match', '127.0.0.1', '2025-08-20 10:44:36'),
(12, NULL, 0, 0.0000, 0.0000, 'No Match', '127.0.0.1', '2025-08-20 11:11:10'),
(13, NULL, 0, 0.0000, 0.0000, 'No Match', '127.0.0.1', '2025-08-20 11:12:29'),
(14, NULL, 0, 0.0000, 0.0000, 'No Match', '127.0.0.1', '2025-08-20 11:14:25'),
(15, NULL, 0, 0.0000, 0.0000, 'No Match', '127.0.0.1', '2025-08-20 11:14:58'),
(16, NULL, 0, 0.0000, 0.0000, 'No Match', '127.0.0.1', '2025-08-20 11:24:12'),
(17, NULL, 0, 0.0000, 0.0000, 'No Match', '127.0.0.1', '2025-08-20 11:24:32'),
(18, NULL, 0, 0.0000, 0.0000, 'No Match', '127.0.0.1', '2025-08-20 11:35:27'),
(19, NULL, 0, 0.0000, 0.0000, 'No Match', '127.0.0.1', '2025-08-20 15:37:47'),
(20, NULL, 0, 0.0000, 0.0000, 'No Match', '127.0.0.1', '2025-08-21 14:53:17'),
(21, NULL, 0, 0.0000, 0.0000, 'No Match', '127.0.0.1', '2025-08-21 15:18:22'),
(22, NULL, 0, 0.0000, 0.0000, 'No Match', '127.0.0.1', '2025-08-21 15:28:15'),
(23, NULL, 0, 0.0000, 0.0000, 'No Match', '127.0.0.1', '2025-08-21 15:29:56'),
(24, NULL, 0, 0.0000, 0.0000, 'No Match', '127.0.0.1', '2025-08-21 15:34:07'),
(25, NULL, 0, 0.0000, 0.0000, 'No Match', '127.0.0.1', '2025-08-21 15:43:19'),
(26, 9, 1, 0.6911, 0.6911, 'Moderate', '127.0.0.1', '2025-08-21 15:59:59'),
(27, 9, 1, 0.8087, 0.8087, 'Strong', '127.0.0.1', '2025-08-21 16:02:06'),
(28, 6, 1, 0.6538, 0.6538, 'Moderate', '127.0.0.1', '2025-08-21 16:04:10'),
(29, 10, 1, 0.6030, 0.6030, 'Moderate', '127.0.0.1', '2025-08-21 16:04:34'),
(30, 6, 1, 0.6775, 0.6775, 'Moderate', '127.0.0.1', '2025-08-21 16:08:24'),
(31, 6, 1, 0.6555, 0.6555, 'Moderate', '127.0.0.1', '2025-08-21 16:08:36'),
(32, 9, 1, 0.6223, 0.6223, 'Moderate', '127.0.0.1', '2025-08-21 16:09:00'),
(33, 9, 1, 0.6223, 0.6223, 'Moderate', '127.0.0.1', '2025-08-21 16:09:03'),
(34, 9, 1, 0.6223, 0.6223, 'Moderate', '127.0.0.1', '2025-08-21 16:09:06'),
(35, 8, 1, 0.5995, 0.5995, 'Weak', '127.0.0.1', '2025-08-21 16:09:39'),
(36, 9, 1, 0.6446, 0.6446, 'Moderate', '127.0.0.1', '2025-08-21 16:10:12'),
(37, 6, 1, 0.7021, 0.7021, 'Moderate', '127.0.0.1', '2025-08-21 16:26:32'),
(38, 9, 1, 0.6970, 0.6970, 'Moderate', '127.0.0.1', '2025-08-21 16:26:57'),
(39, 6, 1, 0.6078, 0.6078, 'Moderate', '127.0.0.1', '2025-08-21 16:27:13'),
(40, 9, 1, 0.6229, 0.6229, 'Moderate', '127.0.0.1', '2025-08-21 16:27:30'),
(41, 10, 1, 0.6101, 0.6101, 'Moderate', '127.0.0.1', '2025-08-21 16:38:35'),
(42, 6, 1, 0.6193, 0.6193, 'Moderate', '127.0.0.1', '2025-08-21 16:38:59'),
(43, 10, 1, 0.5976, 0.5976, 'Weak', '127.0.0.1', '2025-08-21 16:39:20'),
(44, 6, 1, 0.6242, 0.6242, 'Moderate', '127.0.0.1', '2025-08-21 16:40:01'),
(45, 9, 1, 0.6178, 0.6178, 'Moderate', '127.0.0.1', '2025-08-21 16:40:22'),
(46, 6, 1, 0.6803, 0.6803, 'Moderate', '127.0.0.1', '2025-08-21 16:40:46'),
(47, 9, 1, 0.6563, 0.6563, 'Moderate', '127.0.0.1', '2025-08-21 16:42:08'),
(48, 9, 1, 0.7018, 0.7018, 'Moderate', '127.0.0.1', '2025-08-21 16:42:30'),
(49, 4, 1, 0.6374, 0.6374, 'Moderate', '127.0.0.1', '2025-08-21 17:31:53'),
(50, 4, 1, 0.6755, 0.6755, 'Moderate', '127.0.0.1', '2025-08-21 17:32:17'),
(51, 9, 1, 0.8254, 0.8254, 'Strong', '127.0.0.1', '2025-08-21 17:39:58'),
(52, 4, 1, 0.6917, 0.6917, 'Moderate', '127.0.0.1', '2025-08-21 17:40:23'),
(53, 9, 1, 0.7028, 0.7028, 'Moderate', '127.0.0.1', '2025-08-21 17:40:44'),
(54, 4, 1, 0.7393, 0.7393, 'Moderate', '127.0.0.1', '2025-08-21 17:41:22'),
(55, 10, 1, 0.8030, 0.8030, 'Strong', '127.0.0.1', '2025-08-22 09:57:25'),
(56, 10, 1, 0.8030, 0.8030, 'Strong', '127.0.0.1', '2025-08-22 09:57:29'),
(57, 10, 1, 0.8030, 0.8030, 'Strong', '127.0.0.1', '2025-08-22 09:57:33'),
(58, 9, 1, 0.7905, 0.7905, 'Moderate', '127.0.0.1', '2025-08-22 09:58:38'),
(59, 4, 1, 0.7456, 0.7456, 'Moderate', '127.0.0.1', '2025-08-22 09:59:52'),
(60, 9, 1, 0.8377, 0.8377, 'Strong', '127.0.0.1', '2025-08-22 10:00:14'),
(61, 9, 1, 0.7192, 0.7192, 'Moderate', '127.0.0.1', '2025-08-22 10:00:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `lecturer_id` (`lecturer_id`);

--
-- Indexes for table `attendance_sessions`
--
ALTER TABLE `attendance_sessions`
  ADD PRIMARY KEY (`session_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`),
  ADD UNIQUE KEY `course_code` (`course_code`),
  ADD KEY `program_id` (`program_id`);

--
-- Indexes for table `lecturers`
--
ALTER TABLE `lecturers`
  ADD PRIMARY KEY (`lecturer_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `lecturer_courses`
--
ALTER TABLE `lecturer_courses`
  ADD PRIMARY KEY (`assignment_id`),
  ADD UNIQUE KEY `lecturer_id` (`lecturer_id`,`course_id`,`academic_year`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`program_id`),
  ADD UNIQUE KEY `program_code` (`program_code`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `registration_number` (`registration_number`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `program_id` (`program_id`);

--
-- Indexes for table `student_courses`
--
ALTER TABLE `student_courses`
  ADD PRIMARY KEY (`enrollment_id`),
  ADD UNIQUE KEY `student_id` (`student_id`,`course_id`,`academic_year`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `verification_logs`
--
ALTER TABLE `verification_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_timestamp` (`timestamp`),
  ADD KEY `idx_student_id` (`student_id`);

--
-- Indexes for table `verification_logs_new`
--
ALTER TABLE `verification_logs_new`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_sessions`
--
ALTER TABLE `attendance_sessions`
  MODIFY `session_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `lecturers`
--
ALTER TABLE `lecturers`
  MODIFY `lecturer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `lecturer_courses`
--
ALTER TABLE `lecturer_courses`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `program_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `student_courses`
--
ALTER TABLE `student_courses`
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `verification_logs`
--
ALTER TABLE `verification_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `verification_logs_new`
--
ALTER TABLE `verification_logs_new`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`),
  ADD CONSTRAINT `attendance_ibfk_3` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`lecturer_id`);

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`);

--
-- Constraints for table `lecturer_courses`
--
ALTER TABLE `lecturer_courses`
  ADD CONSTRAINT `lecturer_courses_ibfk_1` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`lecturer_id`),
  ADD CONSTRAINT `lecturer_courses_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`);

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`);

--
-- Constraints for table `student_courses`
--
ALTER TABLE `student_courses`
  ADD CONSTRAINT `student_courses_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `student_courses_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
