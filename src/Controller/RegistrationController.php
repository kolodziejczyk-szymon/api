<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @Route("/registration", name="registration", methods={"POST"})
 */
class RegistrationController extends AbstractController
{
    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var UserPasswordHasherInterface
     */
    private $passwordHasher;

    public function __construct(
        ValidatorInterface $validator,
        UserPasswordHasherInterface $passwordHasher
    ) {
        $this->validator = $validator;
        $this->passwordHasher = $passwordHasher;
    }

    public function __invoke(Request $request)
    {
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
        if ($user instanceof User) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Użytkownik o podanym adresie email już istnieje w systemie'
            ]);
        }

        $user = new User($data['email']);
        $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);
        $user->setAuth($data['auth']);

        $manager = $this->getDoctrine()->getManager();
        $manager->persist($user);
        $manager->flush();

        return new JsonResponse([
            'status' => 'OK',
            'data' => [
                'id' => $user->getId(),
                'email' => $user->getEmail()
            ]
        ]);
    }

    private function validate(array $data)
    {
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
            'auth' => [
                new Constraints\NotBlank(),
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
