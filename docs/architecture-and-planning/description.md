# NATRA — Enterprise HRIS & Real-time Tracking Solution

NATRA is a high-performance, multi-tenant Enterprise Resource Planning (ERP) and tracking ecosystem designed to streamline workforce management, attendance, and task monitoring. Built with a focus on precision, security, and scalability, NATRA empowers organizations to manage their remote and on-field workforce with absolute transparency.

---

## Key Features

### 1. Smart Attendance & Geofencing
NATRA redefines attendance management by integrating geolocation intelligence:
*   **Geofenced Check-In/Out**: Ensure employees are physically present within the authorized office radius before they can log their attendance.
*   **Automated Verification**: Real-time distance calculation between the employee's current coordinates and the designated office location.
*   **Monthly Synopsys**: Comprehensive summaries of presence, tardiness, and absence metrics for HR audit trails.

### 2. Advanced Task Management System
A robust workflow engine that bridges the gap between administrators and field agents:
*   **Dynamic Task Assignment**: Administrators can assign tasks with specific priority levels (Urgent, High, Medium, Low).
*   **Workflow Lifecycle**: Manage tasks through distinct stages: *Pending* → *Accepted* → *In-Progress* → *Completed*.
*   **Field Evidence**: Employees can submit completion notes and GPS-stamped verification upon task finalization.

### 3. Real-time Location & Tracking
Maximize operational efficiency with live visibility:
*   **Live Employee Monitoring**: Continuous location updates for active field personnel.
*   **Location Sharing**: Secure, time-limited tokens to share live tracking data with external clients or third-party partners.
*   **Movement History**: Auditable journey logs to review historical routes and timestamps.

### 4. Intelligent Notification Center
Keep the workforce synchronized with an integrated broadcast system:
*   **Task Alerts**: Instant notifications for newly assigned or updated tasks.
*   **Broadcast Messaging**: Super-admins and admins can send mass notifications with image support for organization-wide announcements.
*   **Read-status Tracking**: Real-time monitoring of notification engagement.

---

## Why Choose NATRA?

### Enterprise-Grade Security
NATRA is built on a foundation of **Multi-Tenant Data Isolation**. Every organization’s data (Admin, Employee, Vehicle, and Tasks) is strictly siloed, ensuring zero data leakage and maintaining the highest standards of corporate privacy.

### Accurate & Auditable Data
By leveraging GPS-stamped actions, NATRA eliminates "buddy punching" and provides authentic data for performance reviews and payroll processing.

### Performance-Driven Architecture
The backend is optimized for high-concurrency real-time tracking, using efficient database indexing and eager-loading relationships to ensure a snappy user experience even as your workforce grows.

---

## Technical Ecosystem
*   **Backend**: Laravel Framework with RESTful API architecture.
*   **Mobile Interface**: Modern, iOS-styled mobile experience for employees.
*   **Security**: Bearer Token authentication (Sanctum) and Tenant-based query scoping.
*   **Monitoring**: Real-time coordinate updates and distance-based geofencing logic.

---
© 2026 NATRA Enterprise Solutions. All rights reserved.
