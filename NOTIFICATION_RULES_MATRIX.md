# Notification Rules Matrix

ตารางนี้เป็นกติกากลางของระบบแจ้งเตือนแบบ role-aware เพื่อให้ backend, polling UI, และการดูแลระบบอ้างอิง event เดียวกันโดยไม่กระจายข้อความหรือ recipient rule หลายจุด

| event_key | business_description | recipients | excluded_roles | trigger_source | title_template | message_template | target_url_pattern | target_entity_type | target_entity_id_behavior | priority | dedup_strategy | notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| `attendance_log_submitted` | ผู้ใช้สร้างหรือส่งรายการลงเวลาเวรใหม่เข้าสู่ขั้นตอนรอตรวจสอบ | ผู้มีสิทธิ์ `can_approve_logs` ตามขอบเขตแผนกที่เข้าถึงได้ | `staff` ทั่วไป, `finance` ที่ไม่มี `can_approve_logs`, ผู้ใช้นอก reviewer scope | การบันทึกลงเวลาเวรใหม่จาก `time.php` หรือ flow ที่ทำให้รายการกลายเป็น pending | `มีรายการลงเวลาเวรรอตรวจสอบ` | `มีรายการลงเวลาเวรใหม่เข้าสู่คิวรอตรวจสอบ` | `approval_queue.php` | `approval_queue` | ใช้ `null` เพราะไม่อิงรายการเดี่ยว | `normal` | ไม่สร้างรายแถวให้ reviewer แต่ถูกรวมใน `reviewer_queue_pending_summary` | เป็นต้นทางของการ sync queue aggregate |
| `attendance_log_approved` | รายการลงเวลาเวรของเจ้าของรายการได้รับการอนุมัติแล้ว | เจ้าของรายการเท่านั้น | checker คนอื่น, staff คนอื่น, finance, ผู้ใช้ที่ไม่ใช่เจ้าของรายการ | การอนุมัติรายการจาก approval queue หรือ flow อนุมัติที่เทียบเท่า | `รายการลงเวลาเวรได้รับการอนุมัติแล้ว` | `รายการลงเวลาเวรของคุณได้รับการอนุมัติแล้ว` | `time.php?highlight_log={time_log_id}` | `time_log` | ใช้ `time_log_id` ของรายการที่เพิ่งอนุมัติ | `normal` | หนึ่งรายการที่อนุมัติ = หนึ่ง notification ต่อเจ้าของรายการ | ไม่ยิงให้ reviewer เพื่อลด noise |
| `attendance_log_rejected_or_returned` | รายการลงเวลาเวรถูกส่งกลับหรือไม่ผ่านการอนุมัติและต้องการการแก้ไขจากเจ้าของรายการ | เจ้าของรายการเท่านั้น | reviewer คนอื่น, staff คนอื่น, finance, ผู้ใช้อื่นที่ไม่เกี่ยวข้อง | workflow ส่งกลับหรือปฏิเสธรายการใน approval queue หรือ flow อนาคตที่มีเหตุผลการแก้ไข | `รายการลงเวลาเวรต้องแก้ไขเพิ่มเติม` | `รายการลงเวลาเวรของคุณถูกส่งกลับเพื่อแก้ไข กรุณาตรวจสอบรายละเอียด` | `time.php?highlight_log={time_log_id}` | `time_log` | ใช้ `time_log_id` ของรายการที่ถูกส่งกลับ | `high` | หนึ่งเหตุการณ์ส่งกลับ = หนึ่ง notification | เป็น event แบบ action-required ฝั่งผู้ใช้ |
| `approved_log_reopened_for_review` | รายการที่เคยอนุมัติแล้วถูกแก้ไขจนสถานะกลับมา pending และต้องตรวจสอบอีกครั้ง | ผู้มีสิทธิ์ `can_approve_logs` ตามขอบเขตแผนก | `staff` ทั่วไป, `finance` ที่ไม่มีสิทธิ์อนุมัติ | การแก้ไขรายการที่ reset `checked_at` หรือ `checked_by` กลับเป็น `null` | `มีรายการลงเวลาเวรกลับเข้าสู่คิวตรวจสอบ` | `มีรายการที่แก้ไขหลังอนุมัติและต้องตรวจสอบอีกครั้ง` | `approval_queue.php` | `approval_queue` | ใช้ `null` และรวมในระดับคิว | `normal` | รวมเข้ากับ `reviewer_queue_pending_summary` เพื่อไม่ให้ reviewer ถูกแจ้งซ้ำหลายรายการ | ปัจจุบัน sync ผ่าน helper queue กลาง |
| `reviewer_queue_pending_summary` | สรุปจำนวนรายการลงเวลาเวรที่รอตรวจสอบใน scope ของ reviewer | checker, admin, หรือผู้ใช้ใดก็ตามที่มี `can_approve_logs` | `staff`, `finance` ที่ไม่มีสิทธิ์อนุมัติ | sync queue notification หลัง event สำคัญและเมื่อ UI ต้องแสดง badge ล่าสุด | `มีคิวลงเวลาเวรรอตรวจสอบ` | `มีรายการลงเวลาเวรรอตรวจสอบ {pending_count} รายการ` | `approval_queue.php` | `approval_queue` | ใช้ `null` เพราะเป็น notification ระดับคิว | `normal` | single rolling summary ต่อ reviewer; update unread ตัวล่าสุดเมื่อ count เปลี่ยน และ mark read อัตโนมัติเมื่อ count = 0 | เป็น notification แบบ aggregate หลักสำหรับ reviewer side |
| `admin_permission_changed_user` | ผู้ดูแลระบบเปลี่ยน role หรือสิทธิ์การใช้งานของผู้ใช้ | ผู้ใช้ที่ถูกเปลี่ยนสิทธิ์เท่านั้น | ผู้ใช้อื่นทั้งหมด รวมถึง admin actor หาก audit เพียงพอแล้ว | หน้าจัดการผู้ใช้งานหรือหน้าจัดการสิทธิ์ที่มีการเปลี่ยน role หรือ permission จริง | `สิทธิ์การใช้งานของคุณมีการเปลี่ยนแปลง` | `ผู้ดูแลระบบได้ปรับสิทธิ์การใช้งานของคุณ กรุณาตรวจสอบเมนูที่ใช้งานได้` | `profile.php` | `user` | ใช้ `user_id` ของผู้ใช้ที่ได้รับผลกระทบ | `high` | หนึ่ง action เปลี่ยนสิทธิ์ = หนึ่ง notification | ไม่ควรยิงเมื่อเป็น no-op |
| `admin_updated_user_profile` | ผู้ดูแลระบบปรับข้อมูลบัญชีหรือข้อมูลการทำงานสำคัญของผู้ใช้ | ผู้ใช้ที่ถูกแก้ไขข้อมูล | ผู้ใช้อื่นทั้งหมด | หน้าจัดการผู้ใช้งานของ admin เมื่อแก้ username, department, position, avatar, signature หรือข้อมูลสำคัญอื่น | `ข้อมูลผู้ใช้งานของคุณมีการเปลี่ยนแปลง` | `ข้อมูลบัญชีหรือข้อมูลการทำงานของคุณถูกปรับปรุงโดยผู้ดูแลระบบ` | `profile.php` | `user` | ใช้ `user_id` ของผู้ใช้ที่ถูกแก้ไข | `normal` | หนึ่ง meaningful admin edit = หนึ่ง notification | ไม่ควรสร้างจาก field ภายในที่ผู้ใช้ไม่จำเป็นต้องรับรู้ |
| `system_announcement_scoped` | ประกาศระบบหรือประกาศจากผู้ดูแลที่เจาะจงกลุ่มเป้าหมายตาม role, permission หรือ scope | ผู้ใช้ที่อยู่ในกลุ่มเป้าหมายของประกาศเท่านั้น | ผู้ใช้นอกกลุ่มเป้าหมาย | future admin announcement flow หรือ scheduled operational notice | `ประกาศจากระบบ` | `{announcement_text}` | `notifications.php` | `announcement` | ใช้ `announcement_id` ต่อผู้ใช้แต่ละคนเพื่อ dedupe | `low` | หนึ่ง announcement id ต่อหนึ่งผู้ใช้เป้าหมาย | ไม่สร้างซ้ำทุก page load หรือทุก polling รอบ |
| `export_or_report_job_completed` | งานสร้างรายงานแบบ async เสร็จและพร้อมให้ผู้ใช้ดาวน์โหลด | ผู้ใช้ที่เป็นผู้ร้องขอรายงานเท่านั้น | ผู้ใช้อื่นทั้งหมด | future async report/export job worker | `รายงานของคุณพร้อมแล้ว` | `ระบบจัดเตรียมรายงานเรียบร้อยแล้ว กรุณาเข้าดาวน์โหลด` | `my_reports.php` | `report_job` | ใช้ `report_job_id` หรือ `export_job_id` เมื่อระบบ async พร้อมใช้งาน | `normal` | หนึ่ง report job ต่อหนึ่ง notification | เป็น future event เท่านั้นในรอบปัจจุบัน |

## Recipient Mapping Rules

### Staff
- ควรได้รับ: การอนุมัติรายการของตัวเอง, การส่งกลับเพื่อแก้ไขของตัวเอง, การเปลี่ยนสิทธิ์, การแก้ไขข้อมูลบัญชี, ประกาศที่ scope มาถึงตัวเอง
- ไม่ควรได้รับ: คิวรอตรวจสอบของ reviewer, backlog ฝั่ง admin, notification ของรายการคนอื่น

### Checker / User With `can_approve_logs`
- ควรได้รับ: queue summary, reopened-for-review, reviewer-scoped announcement
- ไม่ควรได้รับ: approval completed ของรายการของคนอื่น, permission change ของคนอื่น, profile update ของคนอื่น

### Admin
- ถ้ามี `can_approve_logs` ให้ได้รับ reviewer queue summary ด้วย
- ถ้าไม่มีสิทธิ์อนุมัติ ไม่ควรได้รับ queue notice โดยอัตโนมัติ
- เหมาะกับ admin-scoped announcement หรือ operational alert ที่ act ได้จริง

### Finance
- รับเฉพาะ event ที่ผูกกับ permission จริง
- ไม่รับ reviewer queue ถ้าไม่มี `can_approve_logs`
- ไม่รับ notification ฝั่ง staff หรือ admin ที่ไม่เกี่ยวข้องกับงานตัวเอง

## Deduplication Summary

- `attendance_log_approved`: 1 รายการที่อนุมัติ = 1 notification ต่อเจ้าของรายการ
- `attendance_log_rejected_or_returned`: 1 รายการที่ถูกส่งกลับ = 1 notification ต่อเจ้าของรายการ
- `reviewer_queue_pending_summary`: aggregate แบบ rolling summary ต่อ reviewer และอัปเดต count เดิม
- `approved_log_reopened_for_review`: รวมเข้า queue summary เดิม ไม่ยิงแยกรายรายการ
- `admin_permission_changed_user`: 1 action เปลี่ยนสิทธิ์ = 1 notification
- `admin_updated_user_profile`: 1 meaningful admin edit = 1 notification
- `system_announcement_scoped`: 1 announcement id ต่อ 1 ผู้ใช้เป้าหมาย
- `export_or_report_job_completed`: 1 report job ต่อ 1 notification
