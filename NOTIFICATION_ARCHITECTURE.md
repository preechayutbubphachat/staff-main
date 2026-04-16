# Notification Architecture

## Storage Strategy
- ใช้ตาราง `notifications` ในฐานข้อมูลเป็นแหล่งข้อมูลหลัก
- ใช้ polling แบบ AJAX ทุก 20 วินาทีเพื่ออัปเดต badge และรายการล่าสุด
- ยังไม่ใช้ WebSocket เพื่อให้เสถียรและดูแลง่ายในโครง PHP ปัจจุบัน

## Table Schema
- `id`
- `user_id`
- `type`
- `title`
- `message`
- `target_url`
- `target_entity_type`
- `target_entity_id`
- `is_read`
- `read_at`
- `metadata_json`
- `source_type`
- `actor_user_id`
- `priority`
- `created_at`

## Endpoints
- `ajax/notifications/get_unread_count.php`
- `ajax/notifications/list_recent.php`
- `ajax/notifications/mark_read.php`
- `ajax/notifications/mark_all_read.php`

## Shared Helper Functions
- `app_create_notification(...)`
- `app_notification_event_matrix()`
- `app_notification_rule($eventKey)`
- `app_notification_payload_from_rule($eventKey, $vars = [])`
- `app_get_unread_notification_count(...)`
- `app_get_recent_notifications(...)`
- `app_get_notifications_page_data(...)`
- `app_mark_notification_read(...)`
- `app_mark_all_notifications_read(...)`
- `app_notify_log_approved(...)`
- `app_notify_log_returned(...)`
- `app_notify_reviewer_queue_if_needed(...)`
- `app_notify_permission_changed(...)`
- `app_notify_user_profile_updated(...)`
- `app_notify_system_announcement(...)`
- `app_create_approval_completed_notification(...)`
- `app_sync_reviewer_queue_notifications(...)`

## Event Matrix Strategy
- ใช้ `app_notification_event_matrix()` เป็นกติกากลางของ event-to-recipient mapping
- แต่ละ event ระบุชัดเจน:
  - business meaning
  - recipients
  - excluded roles
  - trigger source
  - title/message template
  - target URL pattern
  - target entity type
  - target entity id behavior
  - priority
  - deduplication strategy
  - read/unread notes
- helper wrapper แต่ละตัวจะอ้างอิง matrix เดียวกันเพื่อลดการเขียนข้อความหรือ recipient rule ซ้ำหลายไฟล์

## UI Structure
- bell icon อยู่ใน navbar
- badge แสดงจำนวน unread
- dropdown แสดงรายการล่าสุด
- ปุ่ม `อ่านทั้งหมด`
- ปุ่ม `ดูทั้งหมด`
- หน้าเต็ม `pages/notifications.php`

## Polling Strategy
- polling ทุก 20 วินาที
- endpoint count ใช้สำหรับอัปเดต badge แบบเบา
- endpoint recent list ใช้ตอนเปิด dropdown
- ถ้า tab ถูกซ่อนอยู่ จะไม่ยิง count ซ้ำ

## Deduplication / Noise Control
- แจ้งเตือน `approval_completed` ของ staff:
  หนึ่งรายการอนุมัติ -> หนึ่ง notification ต่อ time log
- แจ้งเตือน `approval_queue_pending` ของ checker/admin:
  ใช้แบบ aggregated ต่อผู้ตรวจ
  - มีได้สูงสุดหนึ่งรายการ unread ต่อสภาพ queue ปัจจุบัน
  - ถ้าจำนวนรอตรวจเปลี่ยน จะ update หรือสร้างใหม่ตามสถานะ read/unread
  - ถ้าคิวเป็น 0 จะ mark unread queue notice เป็น read อัตโนมัติ

## Login / Permission Safety
- ทุก endpoint ต้อง login ก่อน
- mark read / mark all read ใช้ POST + CSRF
- ผู้ใช้ mark ได้เฉพาะ notification ของตัวเอง
- ไม่มีการเก็บข้อมูลอ่อนไหวหรือข้อมูลผู้ป่วยใน notification
