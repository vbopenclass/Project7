<?php


namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\Annotations\View;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Psr\Cache\InvalidArgumentException;
use Knp\Component\Pager\PaginatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\Response;
use Nelmio\ApiDocBundle\Annotation\Model;
use Swagger\Annotations as SWG;
use \Doctrine\ORM\NonUniqueResultException;

/**
 * Class UserController
 * @package App\Controller
 */
class UserController extends ObjectManagerController
{
    /**
     * @Rest\Get(
     *     path = "/users",
     *     name = "view_users")
     * @SWG\Response(
     *     response=200,
     *     description="Return the list of user",
     *     @Model(type=User::class)
     * )
     * @SWG\Response(
     *     response=204,
     *     description="This resources don't exist"
     * )
     * @SWG\Response(
     *     response=401,
     *     description="Authenticated failed / invalid token"
     * )
     * @SWG\Tag(name="Users")
     * @IsGranted("ROLE_CLIENT")
     * @param UserRepository $userRepository
     * @param Security $security
     * @param PaginatorInterface $pager
     * @param Request $request
     * @param SerializerInterface $serializer
     * @return \FOS\RestBundle\View\View|mixed|Response
     * @throws InvalidArgumentException
     */
    public function viewUsers(UserRepository $userRepository, Security $security, PaginatorInterface $pager, Request $request,
                              SerializerInterface $serializer)
    {
        $key = 'get_user?page=' . $request->query->getInt('page', 1);

        $onCache = $this->adapter->getItem($key);

        if (true === $onCache->isHit()){
            $data = $onCache->get();
            return $data;
        }

        $query = $userRepository->findAllUsersQuery($security->getUser()->getId());

        $paginated = $pager->paginate(
            $query,
            $request->query->getInt('page', 1),
            $request->query->getInt('limit', 2)
        );

        $context = SerializationContext::create()->setGroups(array(
            'Default',
            'items' => array('detail')
        ));

        $data =  new Response($serializer->serialize($paginated, 'json', $context));
        $this->cache->saveItem($key, $data);

        return $data;
    }

    /**
     * @Rest\Get(
     *     path = "/users/{userId}",
     *     name = "view_user",
     *     requirements={"id"="\d+"})
     * @View(serializerGroups={"detail"})
     * @SWG\Response(
     *     response=200,
     *     description="Return the details for one user",
     *     @Model(type=User::class)
     * )
     * @SWG\Response(
     *     response=204,
     *     description="This resource doesn't exist"
     * )
     * @SWG\Response(
     *     response=401,
     *     description="Authenticated failed / invalid token"
     * )
     * @SWG\Tag(name="Users")
     * @param $userId
     * @param UserRepository $userRepository
     * @param Security $security
     * @return View
     * @throws InvalidArgumentException
     * @throws NonUniqueResultException
     * @IsGranted("ROLE_CLIENT")
     */
    public function viewUser($userId, UserRepository $userRepository, Security $security)
    {
        $key = 'get_user_' . $userId;

        $onCache = $this->adapter->getItem($key);

        if (true === $onCache->isHit()){
            $data = $onCache->get();
            return $data;
        }
        $data = $userRepository->findOneUser($security->getUser()->getId(), $userId);
        $this->cache->saveItem($key, $data);

        return $data;
    }

    /**
     * @Rest\Post(
     *     path = "/users",
     *     name = "new_user")
     * @View(serializerGroups={"credentials"})
     * @SWG\Response(
     *     response=201,
     *     description="New user has been created",
     *     @Model(type=User::class)
     * )
     * @SWG\Response(
     *     response=401,
     *     description="Authenticated failed / invalid token"
     * )
     * @SWG\Response(
     *     response=400,
     *     description="The user hasn't been created // Bad request"
     * )
     * @SWG\Parameter(
     *     name="clientId",
     *     in="header",
     *     required=true,
     *     type="integer",
     *     description="Implement ClientId from headers"
     * )
     * @SWG\Tag(name="Users")
     * @ParamConverter("user", converter="fos_rest.request_body")
     * @IsGranted("ROLE_CLIENT")
     * @param User $user
     * @param EntityManagerInterface $manager
     * @param ClientRepository $repository
     * @param Security $security
     * @param Request $request
     * @return View|\FOS\RestBundle\View\View
     * @throws InvalidArgumentException
     * @throws NonUniqueResultException
     */
    public function newUser(User $user, EntityManagerInterface $manager, ClientRepository $repository, Security $security,
                            Request $request)
    {
        $client = $repository->findClient($security->getUser()->getId());
        $user->setClient($client);

        $hash = password_hash($user->getPassword(), PASSWORD_BCRYPT);
        $user->setPassword($hash);

        $manager->persist($user);
        $manager->flush();

        $keyAll = 'get_user?page=' . $request->query->getInt('page', 1);
        $keyOnce = 'get_user_' . $user->getId();

        $cacheAll = $this->adapter->getItem($keyAll);
        $cacheOnce = $this->adapter->getItem($keyOnce);

        if (true === $cacheAll->isHit()){
            $this->adapter->clear();
        } elseif (true === $cacheOnce->isHit()) {
            $this->adapter->clear();
        }

        return $this->view($user, 201);
    }

    /**
     * @Rest\Put(
     *     path = "/users/{userId}",
     *     name = "modify_user")
     * @View(serializerGroups={"credentials"}, statusCode="201")
     * @SWG\Response(
     *     response=201,
     *     description="User has been modified",
     *     @Model(type=User::class)
     * )
     * @SWG\Response(
     *     response=401,
     *     description="Authenticated failed / invalid token"
     * )
     * @SWG\Response(
     *     response=400,
     *     description="The user hasn't been modified // Bad request"
     * )
     * @SWG\Parameter(
     *     name="clientId",
     *     in="header",
     *     required=true,
     *     type="integer",
     *     description="Implement ClientId from headers"
     * )
     * @SWG\Tag(name="Users")
     * @ParamConverter("user", converter="fos_rest.request_body")
     * @param User $user
     * @param $userId
     * @param UserRepository $repository
     * @param EntityManagerInterface $manager
     * @IsGranted("ROLE_CLIENT")
     * @return \FOS\RestBundle\View\View
     * @throws InvalidArgumentException
     * @throws NonUniqueResultException
     */
    public function modifyUser(User $user, $userId, UserRepository $repository, EntityManagerInterface $manager, Request $request)
    {
        $registeredUser = $repository->findUser($userId);

        $registeredUser
            ->setUsername($user->getUsername())
            ->setPassword(password_hash($user->getPassword(), PASSWORD_BCRYPT))
            ->setEmail($user->getEmail());

        $manager->persist($registeredUser);
        $manager->flush();

        $keyAll = 'get_user?page=' . $request->query->getInt('page', 1);
        $keyOnce = 'get_user_' . $user->getId();

        $cacheAll = $this->adapter->getItem($keyAll);
        $cacheOnce = $this->adapter->getItem($keyOnce);

        if (true === $cacheAll->isHit()){
            $this->adapter->clear();
        } elseif (true === $cacheOnce->isHit()) {
            $this->adapter->clear();
        }

        return $this->view($registeredUser, 201);
    }

    /**
     * @Rest\Delete(
     *     path = "/users/{userId}",
     *     name = "delete_user")
     * @View(serializerGroups={"credentials"}, statusCode="204")
     * @SWG\Response(
     *     response=204,
     *     description="To delete an user",
     *     @Model(type=User::class)
     * )
     * @SWG\Response(
     *     response=401,
     *     description="Authenticated failed / invalid token"
     * )
     * @SWG\Response(
     *     response=400,
     *     description="The user hasn't been deleted // Bad request"
     * )
     * @SWG\Tag(name="Users")
     * @param $userId
     * @param UserRepository $repository
     * @param EntityManagerInterface $manager
     * @throws InvalidArgumentException
     * @throws NonUniqueResultException
     * @IsGranted("ROLE_CLIENT")
     */
    public function deleteUser($userId, UserRepository $repository, EntityManagerInterface $manager, Request $request)
    {
        $registeredUser = $repository->findUser($userId);

        $manager->remove($registeredUser);
        $manager->flush();

        $keyAll = 'get_user_?page=' . $request->query->getInt('page', 1);
        $keyOnce = 'get_user_' . $userId;

        $cacheAll = $this->adapter->getItem($keyAll);
        $cacheOnce = $this->adapter->getItem($keyOnce);

        if (true === $cacheAll->isHit()){
            $this->adapter->clear();
        } elseif (true === $cacheOnce->isHit()) {
            $this->adapter->clear();
        }
    }
}
