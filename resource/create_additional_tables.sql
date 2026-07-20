create table if not exists import(id int(11) auto_increment primary key, file_name varchar(255) not null, generated_date date not null, imported_date timestamp not null);

create index if not exists stop_time_location_schedule_idx on stop_time(location, schedule);
create index if not exists z_stop_time_location_z_schedule_idx on z_stop_time(location, z_schedule);

drop view if exists bidirectional_fixed_link;
create view bidirectional_fixed_link as 
select mode, origin, destination, duration, start_time, end_time, priority, start_date, end_date, monday, tuesday, wednesday, thursday, friday, saturday, sunday from additional_fixed_link
union select mode, destination, origin, duration, start_time, end_time, priority, start_date, end_date, monday, tuesday, wednesday, thursday, friday, saturday, sunday from additional_fixed_link;