-- SQLite schema for Talha Tracker

-- Enable foreign key constraints
PRAGMA foreign_keys = ON;

-- Clients table
CREATE TABLE clients (
    client_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Projects table
CREATE TABLE projects (
    project_id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    notes TEXT,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients (client_id) ON DELETE CASCADE
);

-- Work sessions table
CREATE TABLE work_sessions (
    session_id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP,
    duration_minutes INTEGER,  -- Calculated field for quick access
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects (project_id) ON DELETE CASCADE
);

-- Create indexes for performance
CREATE INDEX idx_projects_client_id ON projects (client_id);
CREATE INDEX idx_work_sessions_project_id ON work_sessions (project_id);
CREATE INDEX idx_work_sessions_start_time ON work_sessions (start_time);
CREATE INDEX idx_work_sessions_end_time ON work_sessions (end_time);

-- Create a view for work logs (as shown in work_logs.html)
CREATE VIEW work_logs AS
SELECT 
    ws.session_id,
    ws.start_time,
    ws.end_time,
    ws.duration_minutes,
    ws.notes as session_notes,
    p.project_id,
    p.name as project_name,
    c.client_id,
    c.name as client_name
FROM work_sessions ws
JOIN projects p ON ws.project_id = p.project_id
JOIN clients c ON p.client_id = c.client_id
ORDER BY ws.start_time DESC;

-- Create a view for daily summary (for dashboard)
CREATE VIEW daily_summary AS
SELECT 
    date(start_time) as work_date,
    sum(duration_minutes) as total_minutes,
    count(session_id) as session_count
FROM work_sessions
GROUP BY date(start_time)
ORDER BY work_date DESC;

-- Create a view for project summary (for reports)
CREATE VIEW project_summary AS
SELECT 
    p.project_id,
    p.name as project_name,
    c.name as client_name,
    sum(ws.duration_minutes) as total_minutes,
    count(ws.session_id) as session_count
FROM work_sessions ws
JOIN projects p ON ws.project_id = p.project_id
JOIN clients c ON p.client_id = c.client_id
GROUP BY p.project_id
ORDER BY total_minutes DESC;

-- Create a view for client summary (for reports)
CREATE VIEW client_summary AS
SELECT 
    c.client_id,
    c.name as client_name,
    count(distinct p.project_id) as project_count,
    sum(ws.duration_minutes) as total_minutes,
    count(ws.session_id) as session_count
FROM clients c
LEFT JOIN projects p ON c.client_id = p.client_id
LEFT JOIN work_sessions ws ON p.project_id = ws.project_id
GROUP BY c.client_id
ORDER BY total_minutes DESC;

-- Trigger to update duration_minutes when a work session ends
CREATE TRIGGER calculate_duration_after_update
AFTER UPDATE OF end_time ON work_sessions
WHEN NEW.end_time IS NOT NULL AND OLD.end_time IS NULL
BEGIN
    UPDATE work_sessions
    SET duration_minutes = ROUND((JULIANDAY(NEW.end_time) - JULIANDAY(NEW.start_time)) * 24 * 60)
    WHERE session_id = NEW.session_id;
END;

-- Trigger to update the updated_at timestamp for clients
CREATE TRIGGER update_client_timestamp
AFTER UPDATE ON clients
BEGIN
    UPDATE clients
    SET updated_at = CURRENT_TIMESTAMP
    WHERE client_id = NEW.client_id;
END;

-- Trigger to update the updated_at timestamp for projects
CREATE TRIGGER update_project_timestamp
AFTER UPDATE ON projects
BEGIN
    UPDATE projects
    SET updated_at = CURRENT_TIMESTAMP
    WHERE project_id = NEW.project_id;
END;

-- Trigger to update the updated_at timestamp for work_sessions
CREATE TRIGGER update_work_session_timestamp
AFTER UPDATE ON work_sessions
BEGIN
    UPDATE work_sessions
    SET updated_at = CURRENT_TIMESTAMP
    WHERE session_id = NEW.session_id;
END;