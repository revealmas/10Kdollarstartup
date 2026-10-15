-- Isolated project: 10K Dollar Startup only. Never apply to MAFSAN-DEMO.
begin;
revoke execute on function public.rls_auto_enable() from public,anon,authenticated;
create table if not exists public.applypilot_state (
 id smallint primary key check(id=1),
 version bigint not null default 0 check(version>=0),
 sealed_payload text,
 updated_at timestamptz not null default now()
);
alter table public.applypilot_state enable row level security;
drop policy if exists service_role_only on public.applypilot_state;
create policy service_role_only on public.applypilot_state for all to service_role using(true) with check(true);
revoke all on public.applypilot_state from public,anon,authenticated;
grant select,insert,update on public.applypilot_state to service_role;
insert into public.applypilot_state(id) values(1) on conflict(id) do nothing;
create or replace function public.applypilot_compare_swap(expected_version bigint,new_payload text)
returns boolean language plpgsql security invoker set search_path=pg_catalog as $$
declare changed integer;
begin
 if octet_length(new_payload)>10485760 then raise exception 'payload too large'; end if;
 update public.applypilot_state set sealed_payload=new_payload,version=version+1,updated_at=now()
 where id=1 and version=expected_version;
 get diagnostics changed=row_count;
 return changed=1;
end; $$;
revoke execute on function public.applypilot_compare_swap(bigint,text) from public,anon,authenticated;
grant execute on function public.applypilot_compare_swap(bigint,text) to service_role;
create or replace function public.applypilot_compare_swap_json(expected_version bigint,new_payload text)
returns jsonb language sql security invoker set search_path=pg_catalog as $$
 select jsonb_build_object('committed',public.applypilot_compare_swap(expected_version,new_payload));
$$;
revoke execute on function public.applypilot_compare_swap_json(bigint,text) from public,anon,authenticated;
grant execute on function public.applypilot_compare_swap_json(bigint,text) to service_role;
commit;
