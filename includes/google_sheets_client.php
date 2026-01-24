<?php
/**
 * Google Sheets API Client
 * Handles authentication and operations with Google Sheets using Service Account
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Google\Client;
use Google\Service\Sheets;

class GoogleSheetsClient {
    private $client;
    private $service;
    private $serviceAccountEmail;
    private $serviceAccountPath;

    public function __construct() {
        $this->serviceAccountEmail = defined('GOOGLE_SA_EMAIL') ? GOOGLE_SA_EMAIL : '';
        $this->serviceAccountPath = defined('GOOGLE_SA_JSON_PATH') ? GOOGLE_SA_JSON_PATH : '';
        
        $this->initializeClient();
    }

    private function initializeClient() {
        try {
            $this->client = new Client();
            
            // Disable SSL verification for development (NOT recommended for production)
            $this->client->setHttpClient(new \GuzzleHttp\Client([
                'verify' => false
            ]));
            
            // First try the configured service account path
            if (file_exists($this->serviceAccountPath)) {
                $this->client->setAuthConfig($this->serviceAccountPath);
            } 
            // Fallback to the existing credentials.json file
            elseif (file_exists(__DIR__ . '/../credentials.json')) {
                $this->client->setAuthConfig(__DIR__ . '/../credentials.json');
            }
            // Fallback to the GOOGLE_APPLICATION_CREDENTIALS path
            elseif (defined('GOOGLE_APPLICATION_CREDENTIALS') && file_exists(GOOGLE_APPLICATION_CREDENTIALS)) {
                $this->client->setAuthConfig(GOOGLE_APPLICATION_CREDENTIALS);
            }
            else {
                throw new Exception("No valid credentials file found. Please ensure credentials.json exists or configure GOOGLE_SA_JSON_PATH.");
            }
            
            // Set the scopes
            $this->client->setScopes([
                'https://www.googleapis.com/auth/spreadsheets',
                'https://www.googleapis.com/auth/drive'
            ]);
            
            // Set the subject (service account email) if provided
            if ($this->serviceAccountEmail) {
                $this->client->setSubject($this->serviceAccountEmail);
            }
            
            // Initialize the Sheets service
            $this->service = new Sheets($this->client);
            
        } catch (Exception $e) {
            error_log("Google Sheets Client initialization failed: " . $e->getMessage());
            throw new Exception("Failed to initialize Google Sheets client: " . $e->getMessage());
        }
    }

    /**
     * Read data from a Google Sheet
     * 
     * @param string $sheetId The ID of the Google Sheet
     * @param string $range The range to read (e.g., 'Sheet1!A1:Z100')
     * @return array Array of rows
     */
    public function gs_list($sheetId, $range) {
        try {
            $response = $this->service->spreadsheets_values->get($sheetId, $range);
            $values = $response->getValues();
            
            if (empty($values)) {
                return [];
            }
            
            return $values;
            
        } catch (Exception $e) {
            error_log("Google Sheets read error: " . $e->getMessage());
            throw new Exception("Failed to read from Google Sheet: " . $e->getMessage());
        }
    }

    /**
     * Append data to a Google Sheet
     * 
     * @param string $sheetId The ID of the Google Sheet
     * @param string $range The range to append to (e.g., 'Sheet1!A:Z')
     * @param array $values Array of values to append
     * @return array Result of the append operation
     */
    public function gs_append($sheetId, $range, $values) {
        try {
            $body = new \Google\Service\Sheets\ValueRange([
                'values' => [$values] // Wrap in array for single row
            ]);
            
            $params = [
                'valueInputOption' => 'RAW',
                'insertDataOption' => 'INSERT_ROWS'
            ];
            
            $result = $this->service->spreadsheets_values->append(
                $sheetId,
                $range,
                $body,
                $params
            );
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Google Sheets append error: " . $e->getMessage());
            throw new Exception("Failed to append to Google Sheet: " . $e->getMessage());
        }
    }

    /**
     * Update data in a Google Sheet
     * 
     * @param string $sheetId The ID of the Google Sheet
     * @param string $range The range to update (e.g., 'Sheet1!A1:Z1')
     * @param array $values Array of values to update
     * @return array Result of the update operation
     */
    public function gs_update($sheetId, $range, $values) {
        try {
            $body = new \Google\Service\Sheets\ValueRange([
                'values' => [$values]
            ]);
            
            $params = [
                'valueInputOption' => 'RAW'
            ];
            
            $result = $this->service->spreadsheets_values->update(
                $sheetId,
                $range,
                $body,
                $params
            );
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Google Sheets update error: " . $e->getMessage());
            throw new Exception("Failed to update Google Sheet: " . $e->getMessage());
        }
    }

    /**
     * Get sheet metadata
     * 
     * @param string $sheetId The ID of the Google Sheet
     * @return array Sheet metadata
     */
    public function gs_get_sheet_info($sheetId) {
        try {
            $response = $this->service->spreadsheets->get($sheetId);
            return $response;
            
        } catch (Exception $e) {
            error_log("Google Sheets info error: " . $e->getMessage());
            throw new Exception("Failed to get sheet info: " . $e->getMessage());
        }
    }

    /**
     * Check if the service account has access to the sheet
     * 
     * @param string $sheetId The ID of the Google Sheet
     * @return bool True if accessible, false otherwise
     */
    public function gs_check_access($sheetId) {
        try {
            $this->gs_get_sheet_info($sheetId);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

// Global helper functions for backward compatibility
function gs_list($sheetId, $range) {
    static $client = null;
    if ($client === null) {
        $client = new GoogleSheetsClient();
    }
    return $client->gs_list($sheetId, $range);
}

function gs_append($sheetId, $range, $values) {
    static $client = null;
    if ($client === null) {
        $client = new GoogleSheetsClient();
    }
    return $client->gs_append($sheetId, $range, $values);
}

function gs_update($sheetId, $range, $values) {
    static $client = null;
    if ($client === null) {
        $client = new GoogleSheetsClient();
    }
    return $client->gs_update($sheetId, $range, $values);
}

function gs_get_sheet_info($sheetId) {
    static $client = null;
    if ($client === null) {
        $client = new GoogleSheetsClient();
    }
    return $client->gs_get_sheet_info($sheetId);
}

function gs_check_access($sheetId) {
    static $client = null;
    if ($client === null) {
        $client = new GoogleSheetsClient();
    }
    return $client->gs_check_access($sheetId);
}
?>
