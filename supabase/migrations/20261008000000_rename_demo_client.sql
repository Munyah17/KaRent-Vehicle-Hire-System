-- Rename demo client account: John Dube -> Munyah Dube (munyah@demo.test)
update public.users
set name = 'Munyah Dube', email = 'munyah@demo.test'
where email = 'john@demo.test';

update public.clients
set full_name = 'Munyah Dube', email = 'munyah@demo.test'
where email = 'john@demo.test';
