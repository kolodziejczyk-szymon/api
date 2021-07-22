<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Account;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints;



class AccountController extends AbstractController
{
    /**
     * @var ValidatorInterface
     */
    private $validator;

    public function __construct(
        ValidatorInterface $validator
    ) {
        $this->validator = $validator;
    }

    /**
     * @Route("/account", name="create_account", methods={"POST"})
     */
    public function createAccount(Request $request) {
        $data = json_decode($request->getContent(), true) ?? [];
        $errors = $this->validate($data);
        if ($errors->count() > 0) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Formularz zawiera błędy',
                'errors' => $this->parseErrors($errors)
            ]);
        }

        $user = $this->getDoctrine()->getRepository(User::class)->findOneBy([
            'email' => $data['email']
        ]);

        $account = new Account($data['name']);

        $account->setUsername($data['username']);
        $account->setPassword($data['password']);
        $account->setUrl($data['url']);
        $account->setDescription($data['description']);
        $account->setUser($user);

        $manager = $this->getDoctrine()->getManager();
        $manager->persist($account);
        $manager->flush();

        return new JsonResponse([
            'status' => 'OK',
            'data' => [
                'id' => $user->getId(),
                'email' => $user->getEmail()
            ]
        ]);
    }

    /**
     * @Route("/account/all", name="get_account", methods={"POST"})
     */
    public function getAccount(Request $request){
        $data = json_decode($request->getContent(), true) ?? [];

        $user = $this->getDoctrine()->getRepository(User::class)->findOneBy([
            'email' => $data['email']
        ]);

        $allAccounts = $this->getDoctrine()->getRepository(Account::class)->findAllUsersAccounts($user->getId());

        return new JsonResponse([
            'status' => 'OK',
            'data' => [
                'accounts' => $allAccounts,
            ]
        ]);
    }

    private function validate(array $data) {
        return $this->validator->validate($data, new Constraints\Collection([
            'email' => [
                new Constraints\NotBlank(),
                new Constraints\NotNull(),
                new Constraints\Email(),
            ],
            'password' => [
                new Constraints\NotBlank(),
                new Constraints\NotNull(),
            ],
            'username' => [
                new Constraints\NotBlank(),
                new Constraints\NotNull(),
            ],
            'name' => [
                new Constraints\NotBlank(),
                new Constraints\NotNull(),
            ],
            'url' => [
                new Constraints\NotBlank(),
                new Constraints\NotNull(),
            ],
            'description' => [
                new Constraints\NotNull(),
            ]
        ]));
    }

    private function parseErrors(ConstraintViolationListInterface $constraintViolationList)
    {
        $errors = [];
        /** @var ConstraintViolationInterface $violation */
        foreach ($constraintViolationList as $violation) {
            $fieldName = preg_replace('/^\[|]$/', '', $violation->getPropertyPath());
            $errors[$fieldName][] = $violation->getMessage();
        }

        return $errors;
    }

}
