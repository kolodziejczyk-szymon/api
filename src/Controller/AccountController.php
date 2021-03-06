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
        $account->setUsername($data['username']);
        $account->setDescription($data['description']);
        $account->setUser($user);

        $manager = $this->getDoctrine()->getManager();
        $manager->persist($account);
        $manager->persist($user);
        $manager->flush();

        return new JsonResponse([
            'status' => 'OK',
            'data' => [
                'id' => $account->getId(),
                'name' => $account->getName(),
                'email' => $user->getEmail(),
                'username' => $account->getUsername(),
                'password' => $account->getPassword(),
                'url' => $account->getUrl(),
                'description' => $account->getDescription(),
            ]
        ]);
    }

    /**
     * @Route("/account/all", name="get_account", methods={"POST"})
     */
    public function getAllAccountsAction(Request $request){
        $data = json_decode($request->getContent(), true) ?? [];

        $user = $this->getDoctrine()->getRepository(User::class)->findOneBy([
            'email' => $data['email']
        ]);

        $accounts = $this->getDoctrine()->getRepository(Account::class)->findBy(
            ['user' => $user]);

        if ($accounts) {
            foreach($accounts as $item) {
                $arrayCollection[] = array(
                    'id' => $item->getId(),
                    'name' => $item->getName(),
                    'description' => $item->getDescription(),
                    'username' => $item->getUsername(),
                    'password' => $item->getPassword(),
                    'url' => $item->getUrl(),
                    // ... Same for each property you want
                );
            }            
        } else {
            $arrayCollection = [];
        }
        
        return new JsonResponse([
            'status' => 'OK',
            'data' => [
                'accounts' => $arrayCollection,
            ]
        ]);
    }

   /**
     * @Route("/account/edit", methods={"PUT"})
     */
    public function updateAccountAction(Request $request)
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $id = $data['id'];

        $entityManager = $this->getDoctrine()->getManager();
        $account = $entityManager->getRepository(Account::class)->find($id);

        if (!$account) {
            throw $this->createNotFoundException(
                'No product found for id '.$id
            );
        }

        $account->setName($data['name']);
        $account->setDescription($data['description']);
        $account->setUsername($data['username']);
        $account->setPassword($data['password']);
        $account->setUrl($data['url']);
        $entityManager->flush();

        return new JsonResponse([
            'status' => 'OK',
            'data' => [
                'id' => $account->getId(),
            ]
        ]);
    }

       /**
     * @Route("/account/delete/{id}", methods={"DELETE"})
     */
    public function deleteAccountAction(int $id)
    {
        $repository = $this->getDoctrine()->getRepository(Account::class);
        $account = $repository->find($id);

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($account);
        $entityManager->flush();

        return new JsonResponse([
            'status' => 'OK'
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
