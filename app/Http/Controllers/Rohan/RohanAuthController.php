<?php

namespace App\Http\Controllers\Rohan;

use App\Http\Controllers\Controller;
use App\Models\Launcher\ServerSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RohanAuthController extends Controller
{
    private function getConnection()
    {
        return DB::connection('sqlsrv');
    }

    /**
     * Login - Call stored procedure ROHAN4_Login
     * Endpoints: Login3.php, Login3a.php, Login7.php
     */
    public function login(Request $request)
    {
        $id = $request->input('id');
        $pw = $request->input('passwd');
        $ver = $request->input('ver');
        $test = $request->input('test');
        $code = $request->input('code');
        $pcode = $request->input('pcode');
        $nation = $request->input('nation');
        $ip = $this->getClientIP($request);

        // Validation
        if (empty($id)) return response('-1|-2|-1');
        if (empty($nation)) return response('-1|-3|-1');
        if (empty($pcode)) return response('-1|-5|-1');

        // Check maintenance mode
        $maintenance = ServerSetting::getValue('maintenance_mode', '0') === '1';

        try {
            $conn = $this->getConnection();

            // Call stored procedure
            $result = $conn->select("
                DECLARE @user_id INT = -1
                DECLARE @sess_id VARCHAR(36) = SPACE(36)
                DECLARE @run_ver VARCHAR(20) = SPACE(20)
                DECLARE @bill_no INT = -1
                DECLARE @grade INT = -1
                DECLARE @ret INT = -1

                EXEC [dbo].[ROHAN4_Login] 
                    @login_id = ?,
                    @login_pw = ?,
                    @nation = ?,
                    @ver = ?,
                    @test = ?,
                    @ip = ?,
                    @code = ?,
                    @user_id = @user_id OUTPUT,
                    @sess_id = @sess_id OUTPUT,
                    @run_ver = @run_ver OUTPUT,
                    @bill_no = @bill_no OUTPUT,
                    @grade = @grade OUTPUT,
                    @ret = @ret OUTPUT

                SELECT @user_id as user_id, @sess_id as sess_id, @run_ver as run_ver, 
                       @bill_no as bill_no, @grade as grade, @ret as ret
            ", [$id, md5($pw), $nation, $ver, $test, $ip, $code]);

            if (empty($result)) {
                return response('-1');
            }

            $row = $result[0];
            $ret = $row->ret ?? -1;
            $userId = $row->user_id ?? -1;
            $sessId = trim($row->sess_id ?? '');
            $runVer = trim($row->run_ver ?? '');
            $grade = $row->grade ?? -1;

            if ($ret == 0) {
                // Maintenance check
                if ($maintenance && $grade != 250 && $id != 'demons') {
                    return response('-1');
                }

                // Insert into TLobby
                $conn->insert("
                    INSERT INTO [RohanUser].[dbo].[TLobby] (user_id, server_id, char_id) 
                    VALUES (?, 0, 0)
                ", [$userId]);

                $data = implode('|', [$sessId, $userId, $runVer, $grade, 0]);
                return response($data);
            }

            return response($ret);

        } catch (\Exception $e) {
            return response('-1000');
        }
    }

    /**
     * Login Remove / Disconnect
     * Endpoint: LoginRemove.php
     */
    public function loginRemove(Request $request)
    {
        $id = $request->input('id');
        $passwd = $request->input('passwd');

        // Validate ID format
        if (!preg_match('/^[a-z_0-9]+$/i', $id)) {
            return response('ERROR');
        }

        if (empty($id)) {
            return response(-99);
        }

        try {
            $conn = $this->getConnection();

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

            return response('OK');

        } catch (\Exception $e) {
            return response('ERROR');
        }
    }

    /**
     * Send Code - Call stored procedure ROHAN3_SendCode
     * Endpoints: SendCode3.php, SendCode7.php
     */
    public function sendCode(Request $request)
    {
        $id = $request->input('id');
        $pw = $request->input('passwd');
        $ip = $this->getClientIP($request);

        if (empty($id)) {
            return response(-99);
        }

        try {
            $conn = $this->getConnection();

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
            return response(-202);

        } catch (\Exception $e) {
            return response(-1);
        }
    }

    /**
     * Server List
     * Endpoint: ServerList5.php
     */
    public function serverList()
    {
        // Get from database or use default
        $serverList = ServerSetting::getValue(
            'server_list',
            'Odin (MAINTENANCE)|129.212.226.244|22100|3|3|1|0|0|0|International Server|'
        );

        return response($serverList);
    }

    /**
     * Down Flag
     * Endpoint: DownFlag2.php
     */
    public function downFlag()
    {
        $downFlag = ServerSetting::getValue('down_flag', 'ROHAN|1|1|ROHAN|DEFAULT');
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
