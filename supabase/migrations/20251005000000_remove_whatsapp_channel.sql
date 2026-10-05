-- Remove WhatsApp as a notification delivery channel.
-- Replaces the notifications.channel check constraint with one that
-- no longer allows 'whatsapp'. Existing rows keep their values.
alter table public.notifications
  drop constraint if exists notifications_channel_check;

alter table public.notifications
  add constraint notifications_channel_check
    check (channel in ('in_app', 'email', 'sms'));
