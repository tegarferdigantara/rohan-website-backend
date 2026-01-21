<?php

namespace App\Http\Controllers\Rohan;

use App\Http\Controllers\Controller;
use App\Models\Launcher\ServerSetting;
use App\Utils\RohanLogger;
use App\Helpers\CloudflareDebug;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RohanAuthController extends Controller
{
    private function getConnection()
    {
        return DB::connection('sqlsrv');
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

    /**
     * Login - Call stored procedure ROHAN4_Login
     * Endpoints: Login3.asp
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
     * -21: Your account is not registered.
     * -22: Invalid Password.
     * -23: You are already logged in. Do you wish to disconnect? (Takes up to 2 minutes to disconnect.)
     * -30: Invalid Version.
     * -31: Data patch in progress.
     * -32: Invalid version.
     * -33: 20046.
     * -40: Login attempts exceded. Please login through the Rohan homepage.
     * -50: Verification code has not been sent.
     * -51: Time expired for entering your verification code.
     * -52: Invalid Authentication Code.
     * -60: 8000003
     * -61: Invalid Authentication Code.
     * -1000: Server Maintenance in progress.
     */
    public function login(Request $request)
    {
        $startTime = microtime(true);
        $requestId = RohanLogger::generateRequestId();
        $endpoint = 'Login';
        $ip = $this->getClientIP($request);
        
        RohanLogger::logRequest($endpoint, $request, $requestId, $ip);
        
        // Cloudflare proxy debug
        CloudflareDebug::logRequest($request, 'Login3');
        CloudflareDebug::logComparison($request, 'Login3');

        $id = $request->input('id');
        $pw = $request->input('passwd');
        $ver = $request->input('ver');
        $test = $request->input('test');
        $code = $request->input('code');
        $pcode = $request->input('pcode');
        $nation = $request->input('nation');

        // Log parsed parameters
        RohanLogger::logDebug($requestId, 'Parameters', [
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
            RohanLogger::logResponse($endpoint, $requestId, '-1|-2|-1', $startTime);
            return response('-1|-2|-1');
        }
        if (empty($nation)) {
            RohanLogger::logResponse($endpoint, $requestId, '-1|-3|-1', $startTime);
            return response('-1|-3|-1');
        }
        if (empty($pcode)) {
            RohanLogger::logResponse($endpoint, $requestId, '-1|-5|-1', $startTime);
            return response('-1|-5|-1');
        }

        // Check maintenance mode
        $maintenance = false;
        try {
            $maintenance = ServerSetting::getValue('maintenance_mode', '0') === '1';
        } catch (\Exception $e) {
            // Ignore if table doesn't exist
            RohanLogger::logWarning($requestId, 'ServerSetting table not available');
        }

        try {
            $conn = $this->getConnection();

            // Log SQL query
            RohanLogger::logDebug($requestId, 'SQL Query', [
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
                RohanLogger::logError($requestId, 'Empty result from procedure');
                RohanLogger::logResponse($endpoint, $requestId, '-1', $startTime);
                return response('-1');
            }

            $row = $result[0];
            $userId = $row->user_id;
            $sessId = trim($row->sess_id);
            $runVer = trim($row->run_ver);
            $billNo = $row->bill_no;
            $grade = $row->grade;
            $ret = $row->ret;

            // Log SQL result
            RohanLogger::logDebug($requestId, 'SQL Result', [
                'user_id' => $userId,
                'sess_id' => $sessId,
                'run_ver' => $runVer,
                'grade' => $grade,
                'ret' => $ret
            ]);

            // Check maintenance mode (allow grade 250 = admin)
            if ($maintenance && $grade != 250) {
                RohanLogger::logWarning($requestId, 'Login blocked - maintenance mode');
                RohanLogger::logResponse($endpoint, $requestId, '-1', $startTime);
                return response('-1');
            }

            // Check return code
            if ($ret == 0) {
                $data = join('|', [$sessId, $userId, $runVer, $grade, 0]);
                
                // Insert into TLobby table
                try {
                    $conn->insert("
                        INSERT INTO [RohanUser].[dbo].[TLobby] (user_id, server_id, char_id) 
                        VALUES (?, 0, 0)
                    ", [$userId]);
                } catch (\Exception $e) {
                    RohanLogger::logWarning($requestId, 'TLobby insert failed: ' . $e->getMessage());
                }

                RohanLogger::logInfo($requestId, 'Login Success', ['user_id' => $userId, 'grade' => $grade]);
                RohanLogger::logResponse($endpoint, $requestId, $data, $startTime);
                return response($data);
            } else {
                RohanLogger::logWarning($requestId, 'Login Failed', ['ret' => $ret, 'id' => $id]);
                RohanLogger::logResponse($endpoint, $requestId, (string)$ret, $startTime);
                return response((string)$ret);
            }
        } catch (\Exception $e) {
            RohanLogger::logError($requestId, 'Login exception: ' . $e->getMessage());
            RohanLogger::logResponse($endpoint, $requestId, '-1', $startTime);
            return response('-1');
        }
    }

    /**
     * Login Remove / Disconnect
     * Endpoint: LoginRemove.asp
     */
    public function loginRemove(Request $request)
    {
        $startTime = microtime(true);
        $requestId = RohanLogger::generateRequestId();
        $endpoint = 'LoginRemove';
        $ip = $this->getClientIP($request);
        
        RohanLogger::logRequest($endpoint, $request, $requestId, $ip);

        $userId = $request->input('user_id');

        // Validation
        if (empty($userId)) {
            RohanLogger::logResponse($endpoint, $requestId, '-1', $startTime);
            return response('-1');
        }

        try {
            $conn = $this->getConnection();

            // Delete from TLobby
            $affected = $conn->delete("
                DELETE FROM [RohanUser].[dbo].[TLobby] 
                WHERE user_id = ?
            ", [$userId]);

            RohanLogger::logInfo($requestId, 'LoginRemove processed', [
                'user_id' => $userId,
                'affected_rows' => $affected
            ]);

            RohanLogger::logResponse($endpoint, $requestId, '1', $startTime);
            return response('1');
        } catch (\Exception $e) {
            RohanLogger::logError($requestId, 'LoginRemove exception: ' . $e->getMessage());
            RohanLogger::logResponse($endpoint, $requestId, '-1', $startTime);
            return response('-1');
        }
    }

    /**
     * Send Code - Call stored procedure ROHAN3_SendCode
     * Endpoints: SendCode7.asp
     */
    public function sendCode(Request $request)
    {
        $startTime = microtime(true);
        $requestId = RohanLogger::generateRequestId();
        $endpoint = 'SendCode';
        $ip = $this->getClientIP($request);
        
        RohanLogger::logRequest($endpoint, $request, $requestId, $ip);

        $id = $request->input('id');
        $pw = $request->input('passwd');

        // Validation
        if (empty($id) || empty($pw)) {
            RohanLogger::logResponse($endpoint, $requestId, '-1|-1', $startTime);
            return response('-1|-1');
        }

        try {
            $conn = $this->getConnection();
            $pwHash = md5($pw);

            // Call stored procedure
            $result = $conn->select("
                DECLARE @p_id VARCHAR(50) = ?
                DECLARE @p_pw VARCHAR(50) = ?
                DECLARE @p_ip VARCHAR(20) = ?
                
                DECLARE @ret INT = 0

                EXEC [dbo].[ROHAN3_SendCode] 
                    @p_id, @p_pw, @p_ip,
                    @ret OUTPUT

                SELECT @ret as ret
            ", [$id, $pwHash, $ip]);

            if (empty($result)) {
                RohanLogger::logError($requestId, 'Empty result from SendCode procedure');
                RohanLogger::logResponse($endpoint, $requestId, '-1', $startTime);
                return response('-1');
            }

            $ret = $result[0]->ret;

            RohanLogger::logInfo($requestId, 'SendCode processed', ['ret' => $ret]);
            RohanLogger::logResponse($endpoint, $requestId, (string)$ret, $startTime);
            return response((string)$ret);
        } catch (\Exception $e) {
            RohanLogger::logError($requestId, 'SendCode exception: ' . $e->getMessage());
            RohanLogger::logResponse($endpoint, $requestId, '-1', $startTime);
            return response('-1');
        }
    }

    /**
     * Server List
     * Endpoint: ServerList5.asp
     */
    public function serverList(Request $request)
    {
        $startTime = microtime(true);
        $requestId = RohanLogger::generateRequestId();
        $endpoint = 'ServerList';
        $ip = $this->getClientIP($request);
        
        RohanLogger::logRequest($endpoint, $request, $requestId, $ip);

        // Cloudflare proxy debug
        CloudflareDebug::logRequest($request, 'ServerList5');
        CloudflareDebug::logComparison($request, 'ServerList5');

        // Get from database or use default
        $serverList = 'Testing|127.0.0.1|22100|3|3|1|0|0|0|Lorem Ipsum|';
        
        try {
            $serverList = ServerSetting::getValue(
                'server_list',
                'Testing|127.0.0.1|22100|3|3|1|0|0|0|Lorem Ipsum|'
            );
        } catch (\Exception $e) {
            // Use default if table doesn't exist
            RohanLogger::logWarning($requestId, 'ServerSetting table not available, using default');
        }

        RohanLogger::logInfo($requestId, 'ServerList requested');
        RohanLogger::logResponse($endpoint, $requestId, $serverList, $startTime);
        return response($serverList);
    }

    /**
     * Down Flag
     * Endpoint: DownFlag2.asp
     */
    public function downFlag(Request $request)
    {
        $startTime = microtime(true);
        $requestId = RohanLogger::generateRequestId();
        $endpoint = 'DownFlag';
        $ip = $this->getClientIP($request);
        
        RohanLogger::logRequest($endpoint, $request, $requestId, $ip);

        // Get from database or use default (1 = down, 0 = up)
        $downFlag = '0';
        
        try {
            $downFlag = ServerSetting::getValue('down_flag', '0');
        } catch (\Exception $e) {
            // Use default if table doesn't exist
            RohanLogger::logWarning($requestId, 'ServerSetting table not available, using default');
        }

        RohanLogger::logInfo($requestId, 'DownFlag requested', ['flag' => $downFlag]);
        RohanLogger::logResponse($endpoint, $requestId, $downFlag, $startTime);
        return response($downFlag);
    }
}
