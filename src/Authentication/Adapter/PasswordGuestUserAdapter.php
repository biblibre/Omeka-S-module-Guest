<?php
namespace GuestUser\Authentication\Adapter;
use Omeka\Authentication\Adapter\PasswordAdapter;
use Doctrine\ORM\EntityRepository;
use Zend\Authentication\Adapter\AbstractAdapter;
use Zend\Authentication\Result;

/**
 * Auth adapter for checking passwords through Doctrine.
 */
class PasswordGuestUserAdapter extends PasswordAdapter
{
    protected $token_repository;

    /**
     * {@inheritDoc}
     */
    public function authenticate()
    {
        $user = $this->repository->findOneBy(['email' => $this->identity]);
        if (!$user || !$user->isActive()) {
            return new Result(Result::FAILURE_IDENTITY_NOT_FOUND, null,
                ['User not found.']);
        }

        if ($user->getRole()=='guest') {
            $guest = $this->token_repository->findOneBy(['email' => $this->identity]);

            if (!$guest->isConfirmed())
                return new Result(Result::FAILURE, null, ['Your account has not been activated']);

        }
        if (!$user->verifyPassword($this->credential)) {

            return new Result(Result::FAILURE_CREDENTIAL_INVALID, null,
                ['Invalid password.']);
        }

        return new Result(Result::SUCCESS, $user);
    }

    public function setTokenRepository($token_repository) {
        $this->token_repository=$token_repository;
    }

}
