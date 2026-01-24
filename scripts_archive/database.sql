-- Table for storing FMS sheets
CREATE TABLE IF NOT EXISTS fms_sheets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sheet_id VARCHAR(255) NOT NULL,
    tab_name VARCHAR(255) NOT NULL,
    label VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_sheet_tab (sheet_id, tab_name)
);

-- Table for storing FMS sheet metadata
CREATE TABLE IF NOT EXISTS fms_sheet_metadata (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sheet_id VARCHAR(255) NOT NULL,
    last_t1_value VARCHAR(255),
    last_u1_value VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_sheet (sheet_id)
);

-- Table for storing FMS tasks
CREATE TABLE IF NOT EXISTS fms_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sheet_id VARCHAR(255) NOT NULL,
    unique_key VARCHAR(255),
    step_name VARCHAR(255),
    planned VARCHAR(255),
    actual VARCHAR(255),
    status VARCHAR(255),
    duration VARCHAR(255),
    doer_name VARCHAR(255),
    department VARCHAR(255),
    task_link VARCHAR(255),
    sheet_label VARCHAR(255),
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table for tracking FMS sheet sync state
CREATE TABLE IF NOT EXISTS fms_sheet_sync (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sheet_id VARCHAR(255) NOT NULL,
    last_t1 VARCHAR(255),
    last_u1 VARCHAR(255),
    last_r1 VARCHAR(255),
    last_synced TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(sheet_id)
); 