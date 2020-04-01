<?php
/* vim: set expandtab sw=4 ts=4 ai smartindent sm ignorecase nu: */
/*
 이 파일을 vim 에서 연 뒤에 아래의 명령 두 개를 실행하면 위에 있는 vim 옵션이 적용됨.
 :set modeline
 :e
*/

/*
 * 커스텀 로그인 구현 클래스
 *
 * AuthResult 를 리턴하는 getAuthenticatedUserInfo()
 * 메소드를 구현해야 한다.
 */

namespace XnLoginService\Customs\Classes;

use XnLoginService\Classes\AuthResult;
use XnLoginService\Classes\SsoUserType;
use GuzzleHttp;

class CustomAuthImpl
{

    private $loginApiUrl = 'http://mmu-cms.xinics.kr/customs/sso/login_check.php';

    public function __construct()
    {
    }

    public function __destruct()
    {
    }

    /**
     *@return AuthResult  시스템 에러나 로그인 실패가 발생하는 경우 모두 예외를 발생
     */
    public function getAuthenticatedUserInfo()
    {
        \Monolog\Registry::
            default()->info('getAuthenticatedUserInfo');

        // 사용자 아이디 얻음. 아이디가 있는 경우만 로그인 시킴.
        if (!isset($_REQUEST['login_user_id']) || empty(trim($_REQUEST['login_user_id']))) {
            throw new \Exception('invalid input');
        }

        $user_id = trim($_REQUEST['login_user_id']);
        $password = trim($_REQUEST['login_user_password']);

        // ToKen을 발급 받음
        $params = $this->getInfoParams()
        $getTokenUrl = $this->loginApiUrl;
        $userInfo = json_decode($this->callApi($params, $getStuUrl));
       
        $result = $userInfo['result'};

        if (empty($userInfo)) {
            throw new \Exception('Invalid Response');
        }

        if ($result == false) {
            \Monolog\Registry::
                default()->error('fail', [
                    'userId' => $user_id
                ]);

            throw new \Exception('Login Failed.');
        }

        if ($strTok !== true) {
            throw new \Exception('Invalid Response');
        }

        $userNo = $userInfo->LoginUser->user_id;
        $userName = $userInfo->LoginUser->user_name;
        $userType = $userInfo->LoginUser->user_type;
        $userEmail = $userInfo->LoginUser->email_address;
        $userDept = $userInfo->LoginUser->dept_group;

        $authResult = new AuthResult();
        $authResult->setUserId($userNo); // 학번/교번을 ID로 사용
        $authResult->setUserNumber($userNo); // 학번은 ID와 동일하게 설정
        $authResult->setUserType($this->getUserType($userNo, $userType));
        $authResult->setDeptGroup($userDept);
        $authResult->setDept($userDept);
        $authResult->setUserName($userName);
        $authResult->setEmailAddress($userEmail);
        return $authResult;
    }

    private function getUserType($user_id, $user_type)
    { 
        $user_type = trim($user_type);
        switch ($user_type) {
            case 'student':    // 학생
                return SsoUserType::STUDENT;
            case 'teacher':    // 교수
                return SsoUserType::PROFESSOR;
            case 'staff':
                return SsoUserType::STAFF;
        }

        \Monolog\Registry::default()->error('unknown user type', ['userId' => $user_id, 'user_type' => $user_type]);
        throw new \Exception("unknown user type:{$user_type}");
    }

    private function getInfoParams($user_id, $password)
    {
        $formParams = [];
        $formParams[0] = ['name' => 'user_id', 'contents' => $user_id];
        $formParams[1] = ['name' => 'password', 'contents' => $password];

        return $formParams;
    }

    private function callApi($formParams, $loginApiUrl)
    {
        // 웹 요청 보내기
        $client = new GuzzleHttp\Client(['timeout'  => 10.0,]);
        try {
            $resp = $client->request(
                'POST',
                $loginApiUrl,
                [
                    'multipart' => $formParams
                ]
            );
        } catch (GuzzleHttp\Exception\RequestException $exc) {
            \Monolog\Registry::
                default()->warn(GuzzleHttp\Psr7\str($exc->getRequest()));
            if ($exc->hasResponse()) {
                \Monolog\Registry::
                    default()->warn(GuzzleHttp\Psr7\str($exc->getResponse()));
            }

            throw $exc;
        }

        $body = $resp->getBody();
        $bodyContent = $body;
        \Monolog\Registry::
            default()->info('Api response', ['body' => $bodyContent]);
        return $bodyContent;
    }
}
