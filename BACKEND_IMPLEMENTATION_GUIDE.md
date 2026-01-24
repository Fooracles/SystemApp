# Backend Implementation Guide for Requirement Modal

## Overview
The frontend sends a `provide_requirement` action that needs to be handled by the backend. This guide shows what needs to be added to `ajax/task_ticket_handler.php`.

## Step 1: Add Case to Switch Statement

In `ajax/task_ticket_handler.php`, add the new case around line 77:

```php
switch ($action) {
    case 'get_items':
        getItems($conn, $user_id);
        break;
    case 'get_item':
        getItem($conn, $user_id);
        break;
    case 'create_item':
        createItem($conn, $user_id);
        break;
    case 'update_item':
        updateItem($conn, $user_id);
        break;
    case 'delete_item':
        deleteItem($conn, $user_id);
        break;
    case 'update_status':
        updateStatus($conn, $user_id);
        break;
    case 'provide_requirement':  // ADD THIS
        provideRequirement($conn, $user_id);  // ADD THIS
        break;  // ADD THIS
    default:
        throw new Exception('Invalid action');
}
```

## Step 2: Create provideRequirement Function

Add this function to `ajax/task_ticket_handler.php` (after the `updateStatus` function):

```php
/**
 * Provide requirement - Save provided description and attachments
 */
function provideRequirement($conn, $user_id) {
    try {
        $item_id = trim($_POST['item_id'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = trim($_POST['status'] ?? 'Provided');
        
        if (empty($item_id)) {
            throw new Exception('Item ID is required');
        }
        
        // Check if item exists
        $check_sql = "SELECT * FROM client_taskflow WHERE unique_id = ? OR id = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, 'ss', $item_id, $item_id);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        $item = mysqli_fetch_assoc($result);
        
        if (!$item) {
            throw new Exception('Item not found');
        }
        
        // Verify it's a Required item
        if ($item['type'] !== 'Required') {
            throw new Exception('Can only provide requirements for Required items');
        }
        
        // Handle attachments
        $provided_attachments = [];
        
        // Get existing provided attachments
        if (!empty($item['provided_attachments'])) {
            $existing = json_decode($item['provided_attachments'], true);
            if (is_array($existing)) {
                $provided_attachments = $existing;
            }
        }
        
        // Handle base64 attachments from client
        if (!empty($_POST['attachments_json'])) {
            $client_attachments = json_decode($_POST['attachments_json'], true);
            if (is_array($client_attachments)) {
                $upload_dir = '../uploads/task_ticket/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                foreach ($client_attachments as $attachment) {
                    if (isset($attachment['fileData']) && !empty($attachment['fileData'])) {
                        try {
                            $file_data = base64_decode($attachment['fileData']);
                            $file_ext = pathinfo($attachment['name'], PATHINFO_EXTENSION);
                            $new_file_name = uniqid() . '_provided_' . time() . '.' . $file_ext;
                            $file_path = $upload_dir . $new_file_name;
                            
                            if (file_put_contents($file_path, $file_data)) {
                                $provided_attachments[] = [
                                    'name' => $attachment['name'],
                                    'size' => $attachment['size'] ?? strlen($file_data),
                                    'type' => $attachment['type'] ?? 'application/octet-stream',
                                    'path' => 'uploads/task_ticket/' . $new_file_name
                                ];
                            }
                        } catch (Exception $e) {
                            error_log("Failed to save provided attachment: " . $e->getMessage());
                        }
                    }
                }
            }
        }
        
        // Also handle FormData file uploads
        if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
            $upload_dir = '../uploads/task_ticket/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
                if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['attachments']['tmp_name'][$i];
                    $name = $_FILES['attachments']['name'][$i];
                    $size = $_FILES['attachments']['size'][$i];
                    $type = $_FILES['attachments']['type'][$i];
                    
                    $file_ext = pathinfo($name, PATHINFO_EXTENSION);
                    $new_file_name = uniqid() . '_provided_' . time() . '_' . $i . '.' . $file_ext;
                    $file_path = $upload_dir . $new_file_name;
                    
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $provided_attachments[] = [
                            'name' => $name,
                            'size' => $size,
                            'type' => $type,
                            'path' => 'uploads/task_ticket/' . $new_file_name
                        ];
                    }
                }
            }
        }
        
        // Update database
        $update_sql = "UPDATE client_taskflow SET 
                        provided_description = ?,
                        provided_attachments = ?,
                        provided_edited_at = NOW(),
                        status = ?,
                        updated_at = NOW()
                       WHERE (unique_id = ? OR id = ?)";
        
        $provided_attachments_json = !empty($provided_attachments) ? json_encode($provided_attachments) : null;
        
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, 'sssss', 
            $description,
            $provided_attachments_json,
            $status,
            $item_id,
            $item_id
        );
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Failed to update requirement');
        }
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Requirement provided successfully'
        ]);
        
    } catch (Exception $e) {
        ob_clean();
        http_response_code(400);
        error_log("provideRequirement error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
```

## Step 3: Update Database Schema (if needed)

Ensure the `client_taskflow` table has these columns:

```sql
ALTER TABLE client_taskflow 
ADD COLUMN IF NOT EXISTS provided_description TEXT NULL,
ADD COLUMN IF NOT EXISTS provided_attachments TEXT NULL,
ADD COLUMN IF NOT EXISTS provided_edited_at DATETIME NULL;
```

## Step 4: Update getItems Function

In the `getItems` function, make sure to include the provided fields in the SELECT statement and response:

```php
// In getItems function, update the SELECT query to include:
SELECT 
    ...,
    provided_description,
    provided_attachments,
    provided_edited_at,
    ...
FROM client_taskflow
...

// In the response array, include:
$item_data = [
    ...
    'provided_description' => $row['provided_description'] ?? null,
    'provided_attachments' => !empty($row['provided_attachments']) 
        ? json_decode($row['provided_attachments'], true) 
        : [],
    'provided_edited_at' => $row['provided_edited_at'] ?? null,
    ...
];
```

## Step 5: Test

1. Test with description only
2. Test with attachments only
3. Test with both description and attachments
4. Test with existing provided data (should update, not replace)
5. Verify "Provided" section appears in detail modal

## Notes

- `provided_description` and `provided_attachments` are separate from the main `description` and `attachments` fields
- Files are saved with `_provided_` prefix in filename to distinguish them
- `provided_edited_at` tracks when the provided data was last updated
- Status is automatically set to "Provided" when submitting
