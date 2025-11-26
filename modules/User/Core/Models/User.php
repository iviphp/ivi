<?php

namespace Modules\User\Core\Models;

use Ivi\Core\ORM\Model;
use DateTimeImmutable;
use Modules\User\Core\ValueObjects\Email;
use Modules\User\Core\ValueObjects\Role;

class User extends Model
{
    private ?int $id;
    private string $fullname;
    private Email $email;
    private ?string $photo = null;
    private ?string $password = null;
    private ?int $roleId = null; // rôle principal (optionnel)
    private array $roles = []; // tous les rôles
    private string $status = 'active';
    private bool $verifiedEmail = false;
    private ?string $coverPhoto = null;
    private ?string $accessToken = null;
    private ?string $refreshToken = null;
    private ?string $bio = null;
    private ?string $phone = null;
    private ?string $username = null;
    private int $messageCount = 0;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private ?string $cityName = null;
    private ?string $countryName = null;
    private ?string $countryImageUrl = null;
    private array $roleNames = [];
    private int $productCount = 0;

    private ?string $photoPublicId = null;
    private ?string $coverPhotoPublicId = null;

    protected static ?string $table = 'users';
    protected static array $fillable = [
        'fullname',
        'email',
        'photo',
        'password',
        'role_id',
        'status',
        'verified_email',
        'cover_photo',
        'access_token',
        'refresh_token',
        'bio',
        'phone',
        'username'
    ];

    public function __construct(
        string $fullname,
        Email $email,
        ?string $photo = null,
        ?string $password = null,
        array $roles = [],
        string $status = 'active',
        bool $verifiedEmail = false,
        ?string $coverPhoto = null,
        ?string $accessToken = null,
        ?string $refreshToken = null,
        ?string $bio = null,
        ?string $phone = null,
        ?string $username = null,
        ?string $cityName = null,
        ?string $countryName = null,
        ?string $countryImageUrl = null,
        ?int $id = null  // ← déplacer à la fin
    ) {
        $this->id = $id;
        $this->fullname = $fullname;
        $this->email = $email;
        $this->photo = $photo;
        $this->password = $password;
        $this->roles = $roles;
        $this->updateRoleNames();
        $this->status = $status;
        $this->verifiedEmail = $verifiedEmail;
        $this->coverPhoto = $coverPhoto;
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
        $this->bio = $bio;
        $this->phone = $phone;
        $this->username = $username;
        $this->cityName = $cityName;
        $this->countryName = $countryName;
        $this->countryImageUrl = $countryImageUrl;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();

        $this->attributes = [
            'fullname'       => $fullname,
            'email'          => (string)$this->email,
            'photo'          => $photo,
            'password'       => $password,
            'role_id'        => $this->roles[0]->getId() ?? null,
            'status'         => $status,
            'verified_email' => $verifiedEmail,
            'cover_photo'    => $coverPhoto,
            'access_token'   => $accessToken,
            'refresh_token'  => $refreshToken,
            'bio'            => $bio,
            'phone'          => $phone,
            'username'       => $username
        ];

        // Synchroniser l’ID avec les attributs
        $this->id = $this->attributes['id'] ?? null;
    }

    public function getAttributes(): array
    {
        return [
            'fullname'       => $this->fullname,
            'email'          => (string) $this->email, // <- ici
            'username'       => $this->username,
            'password'       => $this->password,
            'status'         => $this->status,
            'verified_email' => (int) $this->verifiedEmail,
            'photo'          => $this->photo,
            'cover_photo'    => $this->coverPhoto,
            'access_token'   => $this->accessToken,
            'refresh_token'  => $this->refreshToken,
            'bio'            => $this->bio,
            'phone'          => $this->phone,
            'city_name'      => $this->cityName,
            'country_name'   => $this->countryName,
            'country_image_url' => $this->countryImageUrl,
            'id'             => $this->id,
        ];
    }

    // --- Gestion des rôles ---
    public function addRole(Role $role): void
    {
        foreach ($this->roles as $r) {
            if ($r->getId() === $role->getId()) {
                return; // déjà présent
            }
        }
        $this->roles[] = $role;
        $this->updateRoleNames();
    }

    public function removeRole(Role $role): void
    {
        $this->roles = array_filter(
            $this->roles,
            fn(Role $r) => $r->getId() !== $role->getId()
        );
        $this->updateRoleNames();
    }

    public function hasRole(string $roleName): bool
    {
        foreach ($this->roles as $r) {
            if ($r->getName() === $roleName) {
                return true;
            }
        }
        return false;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): void
    {
        $this->roles = [];
        foreach ($roles as $role) {
            $this->addRole($role);
        }
    }

    private function updateRoleNames(): void
    {
        $this->roleNames = array_map(
            fn(Role $r) => $r->getName(),
            $this->roles
        );
        // roleId = id du premier rôle (optionnel)
        $this->roleId = $this->roles[0]->getId() ?? null;
    }

    /**
     * Vide tous les rôles de l'utilisateur.
     */
    public function clearRoles(): void
    {
        $this->roles = [];
        $this->updateRoleNames();
    }

    public function getRoleNames(): array
    {
        return $this->roleNames;
    }

    public function getVerifiedEmail(): bool
    {
        return $this->verifiedEmail;
    }

    public function getPhotoPublicId(): ?string
    {
        return $this->photoPublicId;
    }
    public function setPhotoPublicId(?string $id): void
    {
        $this->photoPublicId = $id;
    }

    public function getCoverPhotoPublicId(): ?string
    {
        return $this->coverPhotoPublicId;
    }
    public function setCoverPhotoPublicId(?string $id): void
    {
        $this->coverPhotoPublicId = $id;
    }

    // --- Getters / Setters type-safe ---
    public function getId(): int
    {
        return $this->id ?? 0;
    }
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getFullname(): string
    {
        return $this->fullname;
    }
    public function setFullname(string $fullname): void
    {
        $this->fullname = $fullname;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }
    public function setEmail(Email $email): void
    {
        $this->email = $email;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }
    public function setPhoto(?string $photo): void
    {
        $this->photo = $photo;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }
    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function isVerifiedEmail(): bool
    {
        return $this->verifiedEmail;
    }
    public function markEmailVerified(): void
    {
        $this->verifiedEmail = true;
    }

    public function getCoverPhoto(): ?string
    {
        return $this->coverPhoto;
    }
    public function setCoverPhoto(?string $coverPhoto): void
    {
        $this->coverPhoto = $coverPhoto;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }
    public function setAccessToken(?string $token): void
    {
        $this->accessToken = $token;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }
    public function setRefreshToken(?string $token): void
    {
        $this->refreshToken = $token;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }
    public function setBio(?string $bio): void
    {
        $this->bio = $bio;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }
    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }
    public function setUsername(?string $username): void
    {
        $this->username = $username;
    }

    public function getMessageCount(): int
    {
        return $this->messageCount;
    }
    public function incrementMessageCount(): void
    {
        $this->messageCount++;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getCityName(): ?string
    {
        return $this->cityName;
    }
    public function setCityName(?string $city): void
    {
        $this->cityName = $city;
    }

    public function getCountryName(): ?string
    {
        return $this->countryName;
    }
    public function setCountryName(?string $country): void
    {
        $this->countryName = $country;
    }

    public function getCountryImageUrl(): ?string
    {
        return $this->countryImageUrl;
    }
    public function setCountryImageUrl(?string $url): void
    {
        $this->countryImageUrl = $url;
    }

    public function setProductCount(int $count): void
    {
        $this->productCount = $count;
    }

    public function getProductCount(): int
    {
        return $this->productCount;
    }
}
