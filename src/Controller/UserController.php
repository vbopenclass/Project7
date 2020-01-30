<?php


namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Class UserController
 * @package App\Controller
 */
class UserController extends AbstractFOSRestController
{
    /**
     * @Rest\Get(
     *     path = "/users",
     *     name = "view_users")
     * @View(serializerGroups={"list"})
     * @IsGranted("ROLE_CLIENT")
     * @param UserRepository $userRepository
     * @param Security $security
     * @return Response
     */
    public function viewUsers(UserRepository $userRepository, Security $security)
    {
        return $userRepository->findAllUsersQuery($security->getUser()->getId());
    }

    /**
     * @Rest\Get(
     *     path = "/users/{userId}",
     *     name = "view_user",
     *     requirements={"id"="\d+"})
     * @View(serializerGroups={"detail"})
     * @param $userId
     * @param UserRepository $userRepository
     * @param Security $security
     * @return Response
     * @IsGranted("ROLE_CLIENT")
     */
    public function viewUser($userId, UserRepository $userRepository, Security $security)
    {
        return $userRepository->findOneUser($security->getUser()->getId(), $userId);
    }

    /**
     * @Rest\Post(
     *     path = "/users",
     *     name = "new_user")
     * @View(serializerGroups={"credentials"})
     * @ParamConverter("user", converter="fos_rest.request_body")
     * @IsGranted("ROLE_CLIENT")
     * @param User $user
     * @param EntityManagerInterface $manager
     * @param ValidatorInterface $validator
     * @param ClientRepository $repository
     * @param Security $security
     * @return User
     */
    public function newUser(User $user, EntityManagerInterface $manager, ValidatorInterface $validator,
                            ClientRepository $repository, Security $security)
    {
        $client = $repository->findClient($security->getUser()->getId());
        $user->setClient($client[0]);

        $validatorResults = $validator->validate($user, null, null);
        if(count($validatorResults) > 0){
            return $this->view($validatorResults, Response::HTTP_BAD_REQUEST);
        }

        $hash = password_hash($user->getPassword(), PASSWORD_BCRYPT);
        $user->setPassword($hash);

        $manager->persist($user);
        $manager->flush();

        return $user;
    }

    /**
     * @Rest\Put(
     *     path = "/users/{userId}",
     *     name = "modify_user")
     * @View(serializerGroups={"credentials"})
     * @ParamConverter("user", converter="fos_rest.request_body")
     * @param User $user
     * @param $userId
     * @param UserRepository $repository
     * @param EntityManagerInterface $manager
     * @IsGranted("ROLE_CLIENT")
     * @return \FOS\RestBundle\View\View
     */
    public function modifyUser(User $user, $userId, UserRepository $repository, EntityManagerInterface $manager)
    {
        $registeredUser = $repository->findUser($userId);

        if(empty($registeredUser)){
            return $this->view('Cet utilisateur n\'existe pas', Response::HTTP_NOT_FOUND);
        }

        $registeredUser = $registeredUser[0];
        $registeredUser
            ->setUsername($user->getUsername())
            ->setPassword(password_hash($user->getPassword(), PASSWORD_BCRYPT))
            ->setEmail($user->getEmail());

        $manager->persist($registeredUser);
        $manager->flush();

        return $this->view($registeredUser, Response::HTTP_ACCEPTED);
    }

    /**
     * @Rest\Delete(
     *     path = "/users/{userId}",
     *     name = "delete_user")
     * @View(serializerGroups={"credentials"})
     * @param $userId
     * @param UserRepository $repository
     * @IsGranted("ROLE_CLIENT")
     * @return \FOS\RestBundle\View\View

     */
    public function deleteUser($userId, UserRepository $repository, EntityManagerInterface $manager)
    {
        $registeredUser = $repository->findUser($userId);

        if(empty($registeredUser)){

            return $this->view('Cet utilisateur n\'existe pas', Response::HTTP_NOT_FOUND);
        }

        $registeredUser = $registeredUser[0];
        $manager->remove($registeredUser);
        $manager->flush();

        return $this->view('L\'utilisateur a été supprimé');
    }
}
