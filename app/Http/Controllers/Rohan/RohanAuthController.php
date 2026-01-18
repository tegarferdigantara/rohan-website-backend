<?php

namespace App\Http\Controllers\Rohan;

use App\Http\Controllers\Controller;
use App\Models\Launcher\ServerSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RohanAuthController extends Controller
{
    /**
     * Generate unique request ID for logging
     */
    private function getRequestId(): string
    {
        return substr(md5(uniqid(mt_rand(), true)), 0, 8);
    }

    /**
     * Log Rohan auth request
     */
    private function logRequest(string $endpoint, Request $request, string $requestId): void
    {
        $data = [
            'request_id' => $requestId,
            'endpoint' => $endpoint,
            'ip' => $this->getClientIP($request),
            'method' => $request->method(),
            'user_agent' => $request->header('User-Agent'),
            'params' => array_merge(
                $request->query(),
                collect($request->post())->except('passwd')->toArray()
            ),
        ];

        Log::channel('rohan')->info("[$requestId] Request: $endpoint", $data);
    }

    /**
     * Log Rohan auth response
     */
    private function logResponse(string $endpoint, string $requestId, $response, float $startTime): void
    {
        $elapsed = round((microtime(true) - $startTime) * 1000, 2);
        
        Log::channel('rohan')->info("[$requestId] Response: $endpoint", [
            'request_id' => $requestId,
            'endpoint' => $endpoint,
            'response' => is_string($response) ? $response : json_encode($response),
            'elapsed_ms' => $elapsed,
        ]);
    }

    /**
     * Log error
     */
    private function logError(string $endpoint, string $requestId, \Exception $e): void
    {
        Log::channel('rohan')->error("[$requestId] Error: $endpoint", [
            'request_id' => $requestId,
            'endpoint' => $endpoint,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }

    private function getConnection()
    {
        return DB::connection('sqlsrv');
    }

    /**
     * Login - Call stored procedure ROHAN4_Login
     * Endpoints: Login3.php, Login3a.php, Login7.php, Login3.asp
     * - Response ret:
     * -1: Your account is not registered
     * -2: Invalid Password
     * -3: Invalid Account
     * -4: You cannot login from your current IP Address. Please visit our homepage and contact us via email if you have any questions.
     * -5: Your account is under restriction and is unavailable for use. For details, please visit our homepage.
     * -6: You do not meet the age requirement.
     * -7: Please reset your password first before logging in.
     * -8: After 180 days of inactivity, the account will be cancelled.
     * -9: 8000002
     * -10: You're already logged in.
     * -11: This is your private CB Tester account. Thanks for registering!
     * -12: You're already logged in.
     * -13: You're already logged in.
     * -14: E_ALREADY_LOGINED
     * -15: The account is inactive. Go to Rohan home page and access [Inactive Cancellation] to use the account for the game.
     * -16: If you continously try to connect during connection waiting time, connection may be blocked. 
     * -17: For safe data flow, about 3 minutes is given.
     * -18: Login Failed.
     * -19: Login Failed.
     * -20: Login Failed.
     * -1000: Server Maintenance in progress
     * 
     */
    public function login(Request $request)
    {
        $startTime = microtime(true);
        $requestId = $this->getRequestId();
        $endpoint = 'Login';
        
        $this->logRequest($endpoint, $request, $requestId);

        $id = $request->input('id');
        $pw = $request->input('passwd');
        $ver = $request->input('ver');
        $test = $request->input('test');
        $code = $request->input('code');
        $pcode = $request->input('pcode');
        $nation = $request->input('nation');
        $ip = $this->getClientIP($request);

        // Log parsed parameters
        Log::channel('rohan')->debug("[$requestId] Parameters", [
            'id' => $id,
            'ver' => $ver,
            'test' => $test,
            'code' => $code,
            'pcode' => $pcode,
            'nation' => $nation,
            'ip' => $ip,
        ]);

        // Validation
        if (empty($id)) {
            $this->logResponse($endpoint, $requestId, '-1|-2|-1', $startTime);
            return response('-1|-2|-1');
        }
        if (empty($nation)) {
            $this->logResponse($endpoint, $requestId, '-1|-3|-1', $startTime);
            return response('-1|-3|-1');
        }
        if (empty($pcode)) {
            $this->logResponse($endpoint, $requestId, '-1|-5|-1', $startTime);
            return response('-1|-5|-1');
        }

        // Check maintenance mode
        $maintenance = false;
        try {
            $maintenance = ServerSetting::getValue('maintenance_mode', '0') === '1';
        } catch (\Exception $e) {
            // Ignore if table doesn't exist
            Log::channel('rohan')->warning("[$requestId] ServerSetting table not available");
        }

        try {
            $conn = $this->getConnection();

            // Log SQL query
            Log::channel('rohan')->debug("[$requestId] SQL Query", [
                'procedure' => 'ROHAN4_Login',
                'params' => ['id' => $id, 'nation' => $nation, 'ver' => $ver],
            ]);

            // Escape and prepare values
            $pwHash = md5($pw);
            $testVal = (int)$test;
            $codeVal = (int)$code;

            // Call stored procedure - bind each parameter explicitly
            $result = $conn->select("
                DECLARE @p_id VARCHAR(50) = ?
                DECLARE @p_pw VARCHAR(50) = ?
                DECLARE @p_nation VARCHAR(10) = ?
                DECLARE @p_ver VARCHAR(30) = ?
                DECLARE @p_test TINYINT = ?
                DECLARE @p_ip VARCHAR(20) = ?
                DECLARE @p_code INT = ?
                
                DECLARE @user_id INT = -1
                DECLARE @sess_id CHAR(36) = SPACE(36)
                DECLARE @run_ver VARCHAR(30) = SPACE(30)
                DECLARE @bill_no INT = 0
                DECLARE @grade TINYINT = 0
                DECLARE @ret INT = 0

                EXEC [dbo].[ROHAN4_Login] 
                    @p_id, @p_pw, @p_nation, @p_ver, @p_test, @p_ip, @p_code,
                    @user_id OUTPUT,
                    @sess_id OUTPUT,
                    @run_ver OUTPUT,
                    @bill_no OUTPUT,
                    @grade OUTPUT,
                    @ret OUTPUT

                SELECT @user_id as user_id, @sess_id as sess_id, @run_ver as run_ver, 
                       @bill_no as bill_no, @grade as grade, @ret as ret
            ", [$id, $pwHash, $nation, $ver, $testVal, $ip, $codeVal]);

            if (empty($result)) {
                $this->logResponse($endpoint, $requestId, '-1', $startTime);
                return response('-1');
            }

            $row = $result[0];
            $ret = $row->ret ?? -1;
            $userId = $row->user_id ?? -1;
            $sessId = trim($row->sess_id ?? '');
            $runVer = trim($row->run_ver ?? '');
            $grade = $row->grade ?? -1;

            // Log SQL result
            Log::channel('rohan')->debug("[$requestId] SQL Result", [
                'user_id' => $userId,
                'sess_id' => $sessId,
                'run_ver' => $runVer,
                'grade' => $grade,
                'ret' => $ret,
            ]);

            if ($ret == 0) {
                // Maintenance check
                if ($maintenance && $grade != 250 && $id != 'demons') {
                    Log::channel('rohan')->warning("[$requestId] Login blocked - maintenance mode", ['id' => $id]);
                    $this->logResponse($endpoint, $requestId, '-1000', $startTime);
                    return response('-1000');
                }

                // Upsert into TLobby (delete existing first, then insert)
                $conn->statement("
                    DELETE FROM [RohanUser].[dbo].[TLobby] WHERE user_id = ?
                ", [$userId]);
                $conn->insert("
                    INSERT INTO [RohanUser].[dbo].[TLobby] (user_id, server_id, char_id) 
                    VALUES (?, 0, 0)
                ", [$userId]);

                $data = implode('|', [$sessId, $userId, $runVer, $grade, 0]);
                
                Log::channel('rohan')->info("[$requestId] Login SUCCESS", [
                    'user_id' => $userId,
                    'grade' => $grade,
                    'id' => $id,
                ]);
                
                $this->logResponse($endpoint, $requestId, $data, $startTime);
                return response($data);
            }

            Log::channel('rohan')->warning("[$requestId] Login FAILED", [
                'ret' => $ret,
                'id' => $id,
            ]);
            
            $this->logResponse($endpoint, $requestId, $ret, $startTime);
            return response($ret);

        } catch (\Exception $e) {
            $this->logError($endpoint, $requestId, $e);
            return response('-1000');
        }
    }

    /**
     * Login Remove / Disconnect
     * Endpoint: LoginRemove.php, LoginRemove.asp
     */
    public function loginRemove(Request $request)
    {
        $startTime = microtime(true);
        $requestId = $this->getRequestId();
        $endpoint = 'LoginRemove';
        
        $this->logRequest($endpoint, $request, $requestId);

        $id = $request->input('id');
        $passwd = $request->input('passwd');

        // Validate ID format
        if (!preg_match('/^[a-z_0-9]+$/i', $id)) {
            Log::channel('rohan')->warning("[$requestId] Invalid ID format", ['id' => $id]);
            $this->logResponse($endpoint, $requestId, 'ERROR', $startTime);
            return response('ERROR');
        }

        if (empty($id)) {
            $this->logResponse($endpoint, $requestId, -99, $startTime);
            return response(-99);
        }

        try {
            $conn = $this->getConnection();

            Log::channel('rohan')->debug("[$requestId] LoginRemove executing", ['id' => $id]);

            $conn->statement("
                DECLARE @user_id INT
                DECLARE @server_id INT
                DECLARE @char_id INT

                SELECT @user_id = user_id 
                FROM RohanUser.dbo.TUser 
                WHERE login_id = ? AND login_pw = ?

                SELECT @server_id = server_id, @char_id = char_id 
                FROM [RohanUser].[dbo].[TLobby] 
                WHERE user_id = @user_id

                UPDATE [RohanUser].[dbo].[TLobby] 
                SET server_id = 0, char_id = 0
                WHERE user_id = @user_id

                INSERT INTO [RohanUser].[dbo].TDisconnect (char_id, user_id, server_id) 
                VALUES (@char_id, @user_id, @server_id)
            ", [$id, md5($passwd)]);

            Log::channel('rohan')->info("[$requestId] LoginRemove SUCCESS", ['id' => $id]);
            $this->logResponse($endpoint, $requestId, 'OK', $startTime);
            return response('OK');

        } catch (\Exception $e) {
            $this->logError($endpoint, $requestId, $e);
            return response('ERROR');
        }
    }

    /**
     * Send Code - Call stored procedure ROHAN3_SendCode
     * Endpoints: SendCode3.php, SendCode7.php
     */
    public function sendCode(Request $request)
    {
        $startTime = microtime(true);
        $requestId = $this->getRequestId();
        $endpoint = 'SendCode';
        
        $this->logRequest($endpoint, $request, $requestId);

        $id = $request->input('id');
        $pw = $request->input('passwd');
        $ip = $this->getClientIP($request);

        if (empty($id)) {
            $this->logResponse($endpoint, $requestId, -99, $startTime);
            return response(-99);
        }

        try {
            $conn = $this->getConnection();

            Log::channel('rohan')->debug("[$requestId] SendCode executing", ['id' => $id, 'ip' => $ip]);

            $result = $conn->select("
                DECLARE @ret INT = -1

                EXEC [dbo].[ROHAN3_SendCode]
                    @login_id = ?,
                    @login_pw = ?,
                    @ip = ?,
                    @ret = @ret OUTPUT

                SELECT @ret as ret
            ", [$id, md5($pw), $ip]);

            // Original code always returns -202
            Log::channel('rohan')->info("[$requestId] SendCode complete", ['id' => $id]);
            $this->logResponse($endpoint, $requestId, -202, $startTime);
            return response(-202);

        } catch (\Exception $e) {
            $this->logError($endpoint, $requestId, $e);
            return response(-1);
        }
    }

    /**
     * Server List
     * Endpoint: ServerList5.php, ServerList5.asp
     */
    public function serverList(Request $request)
    {
        $startTime = microtime(true);
        $requestId = $this->getRequestId();
        $endpoint = 'ServerList';
        
        $this->logRequest($endpoint, $request, $requestId);

        // Get from database or use default
        $serverList = 'Testing|127.0.0.1|22100|3|3|1|0|0|0|Lorem Ipsum|';
        
        try {
            $serverList = ServerSetting::getValue(
                'server_list',
                'Testing|127.0.0.1|22100|3|3|1|0|0|0|Lorem Ipsum|'
            );
        } catch (\Exception $e) {
            // Use default if table doesn't exist
            Log::channel('rohan')->warning("[$requestId] ServerSetting table not available, using default");
        }

        Log::channel('rohan')->info("[$requestId] ServerList requested");
        $this->logResponse($endpoint, $requestId, $serverList, $startTime);
        return response($serverList);
    }

    /**
     * Down Flag
     * Endpoint: DownFlag2.php, DownFlag2.asp
     */
    public function downFlag(Request $request)
    {
        $startTime = microtime(true);
        $requestId = $this->getRequestId();
        $endpoint = 'DownFlag';
        
        $this->logRequest($endpoint, $request, $requestId);

        $downFlag = 'ROHAN|1|1|ROHAN|DEFAULT';
        
        try {
            $downFlag = ServerSetting::getValue('down_flag', 'ROHAN|1|1|ROHAN|DEFAULT');
        } catch (\Exception $e) {
            // Use default if table doesn't exist
        }

        $this->logResponse($endpoint, $requestId, $downFlag, $startTime);
        return response($downFlag);
    }

    /**
     * Get Client IP Address
     */
    private function getClientIP(Request $request): string
    {
        if ($request->header('X-Forwarded-For')) {
            $ip = explode(',', $request->header('X-Forwarded-For'))[0];
        } elseif ($request->header('X-Real-IP')) {
            $ip = $request->header('X-Real-IP');
        } else {
            $ip = $request->ip();
        }

        return trim($ip);
    }
}
