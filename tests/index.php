<?php

/*
 * Copyright (c) delight.im <info@delight.im>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

header('Content-type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 'stdout');

$db = new PDO('mysql:dbname=php_auth;host=127.0.0.1;charset=utf8', 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

require __DIR__.'/../src/Auth.php';

$auth = new \Delight\Auth\Auth($db);

$result = processRequestData($auth);

showDebugData($auth, $result);

if ($auth->check()) {
	showAuthenticatedUserForm();
}
else {
	showGuestUserForm();
}

function processRequestData(\Delight\Auth\Auth $auth) {
	if (isset($_POST)) {
		if (isset($_POST['action'])) {
			if ($_POST['action'] === 'login') {
				try {
					$auth->login($_POST['email'], $_POST['password'], ($_POST['remember'] == 1));

					return 'ok';
				}
				catch (\Delight\Auth\InvalidEmailException $e) {
					return 'wrong email address';
				}
				catch (\Delight\Auth\InvalidPasswordException $e) {
					return 'wrong password';
				}
				catch (\Delight\Auth\EmailNotVerifiedException $e) {
					return 'email not verified';
				}
				catch (\Delight\Auth\TooManyRequestsException $e) {
					return 'too many requests';
				}
			}
			else if ($_POST['action'] === 'register') {
				try {
					if ($_POST['require_verification'] == 1) {
						$callback = function ($selector, $token) {
							echo '<pre>';
							echo 'Email confirmation';
							echo "\n";
							echo '  >  Selector';
							echo "\t\t\t\t";
							echo htmlspecialchars($selector);
							echo "\n";
							echo '  >  Token';
							echo "\t\t\t\t";
							echo htmlspecialchars($token);
							echo '</pre>';
						};
					}
					else {
						$callback = null;
					}

					return $auth->register($_POST['email'], $_POST['password'], $_POST['username'], $callback);
				}
				catch (\Delight\Auth\InvalidEmailException $e) {
					return 'invalid email address';
				}
				catch (\Delight\Auth\InvalidPasswordException $e) {
					return 'invalid password';
				}
				catch (\Delight\Auth\UserAlreadyExistsException $e) {
					return 'user already exists';
				}
				catch (\Delight\Auth\TooManyRequestsException $e) {
					return 'too many requests';
				}
			}
			else if ($_POST['action'] === 'confirmEmail') {
				try {
					$auth->confirmEmail($_POST['selector'], $_POST['token']);

					return 'ok';
				}
				catch (\Delight\Auth\InvalidSelectorTokenPairException $e) {
					return 'invalid token';
				}
				catch (\Delight\Auth\TokenExpiredException $e) {
					return 'token expired';
				}
				catch (\Delight\Auth\TooManyRequestsException $e) {
					return 'too many requests';
				}
			}
			else if ($_POST['action'] === 'changePassword') {
				try {
					$auth->changePassword($_POST['oldPassword'], $_POST['newPassword']);

					return 'ok';
				}
				catch (\Delight\Auth\NotLoggedInException $e) {
					return 'not logged in';
				}
				catch (\Delight\Auth\InvalidPasswordException $e) {
					return 'invalid password(s)';
				}
			}
			else if ($_POST['action'] === 'logout') {
				$auth->logout();

				return 'ok';
			}
			else {
				throw new Exception('Unexpected action: '.$_POST['action']);
			}
		}
	}

	return null;
}

function showDebugData(\Delight\Auth\Auth $auth, $result) {
	echo '<pre>';

	echo 'Last operation'."\t\t\t\t";
	var_dump($result);
	echo 'Session ID'."\t\t\t\t";
	var_dump(session_id());
	echo "\n";

	echo '$auth->isLoggedIn()'."\t\t\t";
	var_dump($auth->isLoggedIn());
	echo '$auth->check()'."\t\t\t\t";
	var_dump($auth->check());
	echo "\n";

	echo '$auth->getUserId()'."\t\t\t";
	var_dump($auth->getUserId());
	echo '$auth->id()'."\t\t\t\t";
	var_dump($auth->id());
	echo "\n";

	echo '$auth->getEmail()'."\t\t\t";
	var_dump($auth->getEmail());
	echo '$auth->getUsername()'."\t\t\t";
	var_dump($auth->getUsername());
	echo '$auth->isRemembered()'."\t\t\t";
	var_dump($auth->isRemembered());
	echo '$auth->getIpAddress()'."\t\t\t";
	var_dump($auth->getIpAddress());
	echo "\n";

	echo 'Auth::createRandomString()'."\t\t";
	var_dump(\Delight\Auth\Auth::createRandomString());
	echo 'Auth::createUuid()'."\t\t\t";
	var_dump(\Delight\Auth\Auth::createUuid());

	echo '</pre>';
}

function showGeneralForm() {
	echo '<form action="" method="get" accept-charset="utf-8">';
	echo '<button type="submit">Refresh</button>';
	echo '</form>';
}

function showAuthenticatedUserForm() {
	showGeneralForm();

	echo '<form action="" method="post" accept-charset="utf-8">';
	echo '<input type="hidden" name="action" value="changePassword" />';
	echo '<input type="text" name="oldPassword" placeholder="Old password" /> ';
	echo '<input type="text" name="newPassword" placeholder="New password" /> ';
	echo '<button type="submit">Change password</button>';
	echo '</form>';

	echo '<form action="" method="post" accept-charset="utf-8">';
	echo '<input type="hidden" name="action" value="logout" />';
	echo '<button type="submit">Logout</button>';
	echo '</form>';
}

function showGuestUserForm() {
	showGeneralForm();

	echo '<form action="" method="post" accept-charset="utf-8">';
	echo '<input type="hidden" name="action" value="login" />';
	echo '<input type="text" name="email" placeholder="Email" /> ';
	echo '<input type="text" name="password" placeholder="Password" /> ';
	echo '<select name="remember" size="1">';
	echo '<option value="0">Remember? — No</option>';
	echo '<option value="1">Remember? — Yes</option>';
	echo '</select> ';
	echo '<button type="submit">Login</button>';
	echo '</form>';

	echo '<form action="" method="post" accept-charset="utf-8">';
	echo '<input type="hidden" name="action" value="register" />';
	echo '<input type="text" name="email" placeholder="Email" /> ';
	echo '<input type="text" name="password" placeholder="Password" /> ';
	echo '<input type="text" name="username" placeholder="Username (optional)" /> ';
	echo '<select name="require_verification" size="1">';
	echo '<option value="0">Require email confirmation? — No</option>';
	echo '<option value="1">Require email confirmation? — Yes</option>';
	echo '</select> ';
	echo '<button type="submit">Register</button>';
	echo '</form>';

	echo '<form action="" method="post" accept-charset="utf-8">';
	echo '<input type="hidden" name="action" value="confirmEmail" />';
	echo '<input type="text" name="selector" placeholder="Selector" /> ';
	echo '<input type="text" name="token" placeholder="Token" /> ';
	echo '<button type="submit">Confirm email</button>';
	echo '</form>';
}