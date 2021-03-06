<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Groups;
use Hateoas\Configuration\Annotation as Hateoas;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 * @ORM\Table()
 * @Hateoas\Relation(
 *    "self",
 *    href = @Hateoas\Route(
 *        "view_user",
 *        parameters = {"userId" = "expr(object.getId())"},
 *        absolute = true
 *    ),
 *     exclusion = @Hateoas\Exclusion(groups={"detail"})
 * )
 *
 *  * @Hateoas\Relation(
 *    "self",
 *    href = @Hateoas\Route(
 *        "view_users",
 *        absolute = true
 *    ),
 *     exclusion = @Hateoas\Exclusion(groups={"list"})
 * )
 *
 *   @Hateoas\Relation(
 *    "update",
 *    href = @Hateoas\Route(
 *        "modify_user",
 *        parameters = {"userId" = "expr(object.getId())"},
 *        absolute = true
 *    ),
 *     exclusion = @Hateoas\Exclusion(groups={"detail"})
 * )
 *
 *   @Hateoas\Relation(
 *    "delete",
 *    href = @Hateoas\Route(
 *        "delete_user",
 *        parameters = {"userId" = "expr(object.getId())"},
 *        absolute = true
 *    ),
 *     exclusion = @Hateoas\Exclusion(groups={"detail"})
 * )
 */

class User
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Groups({"detail", "list", "credentials"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"detail", "list", "credentials"})
     * @Assert\NotBlank

     */
    private $username;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"credentials"})
     * @Assert\NotBlank
     */
    private $password;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"detail", "credentials"})
     * @Assert\NotBlank
     */
    private $email;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Client", inversedBy="users", cascade={"persist"})
     * @ORM\JoinColumn(name="client_ìd", referencedColumnName="id", nullable=false)
     * @Assert\NotBlank
     */
    private $client;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getClient()
    {
        return $this->client;
    }
    /**
     * @param $client
     * @return $this
     */
    public function setClient ( $client)
    {
        $this->client = $client;
        return $this;
    }


    /**
     * @param array $data
     * @return void
     */
    function createUserObject(array $data)
    {
        $this->setUsername($data['username']);
        $this->setPassword($data['password']);
        $this->setEmail($data['email']);
    }
}
