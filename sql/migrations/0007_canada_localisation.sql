-- iERA Canada: the in-person category is "Canada" (was "UK"); international volunteers are "Global".
UPDATE volunteer_applications SET volunteer_category = 'Canada' WHERE volunteer_category = 'UK';
UPDATE regions SET branch = 'Canada' WHERE branch = 'UK';
UPDATE regions SET name = 'Canada' WHERE name = 'UK' AND parent_id IS NULL;
UPDATE manager_assignments SET volunteer_category = 'Canada' WHERE volunteer_category = 'UK';
UPDATE manager_assignments SET management_role = 'Canada Volunteer Manager' WHERE management_role = 'UK Volunteer Manager';
UPDATE manager_assignments SET management_role = 'City Coordinator' WHERE management_role = 'UK City Coordinator';
UPDATE manager_assignments SET management_role = 'International Volunteer Director' WHERE management_role = 'Global Volunteer Director';
UPDATE group_memberships SET group_slug = 'canada', group_name = 'Canada Volunteers' WHERE group_slug = 'uk';
UPDATE community_posts SET group_slug = 'canada' WHERE group_slug = 'uk';
UPDATE community_events SET group_slug = 'canada' WHERE group_slug = 'uk';
UPDATE notification_rules SET trigger_type = 'category_canada' WHERE trigger_type = 'category_uk';
UPDATE notification_logs SET trigger_type = 'category_canada' WHERE trigger_type = 'category_uk';
UPDATE email_templates SET subject = REPLACE(subject, 'UK Risk Assessment', 'Risk Assessment'), body_html = REPLACE(REPLACE(REPLACE(body_html, 'UK Risk Assessment', 'Risk Assessment'), 'Risk Assessments UK', 'Risk assessments'), 'UK volunteer', 'Canadian volunteer') WHERE tpl_key LIKE 'connect_%';
UPDATE email_templates SET subject = REPLACE(subject, 'UK risk assessment', 'risk assessment'), body_html = REPLACE(body_html, 'UK risk assessment', 'risk assessment') WHERE tpl_key LIKE 'connect_%';
