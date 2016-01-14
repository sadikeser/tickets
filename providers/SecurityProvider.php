<?php
namespace Tickets;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Doctrine\DBAL\Connection;

class SecurityProvider implements UserProviderInterface
{
    private $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public function loadUserByUsername($username)
    {
        $stmt = $this->conn->executeQuery('SELECT * FROM users WHERE login_name = ?', array(strtolower($username)));
        if (!$user = $stmt->fetch()) {
            return null;
        }
        $object = new User($user['login_name'], $user["login_pass"], explode(',', $user['user_role']), true, true, true, true);
        global $app;
        $encoder = $app['security.encoder_factory']->getEncoder($object);
        $password = $encoder->encodePassword($app["request"]->get("_password"), $object->getSalt());
        if ($object !== null && $object->getPassword() == $password) {
            $app['user'] = $object;
            $app['session']->set("username", $username);
        }
        return $object;
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    public function supportsClass($class)
    {
        return $class === 'Symfony\Component\Security\Core\User\User';
    }
}