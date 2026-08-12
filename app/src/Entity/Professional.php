<?php

namespace App\Entity;

use App\Repository\ProfessionalRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: ProfessionalRepository::class)]
#[ORM\Index(name: 'idx_professional_statut_visible', columns: ['statut', 'is_visible'])]
#[ORM\Index(
    name: 'idx_professional_fulltext',
    columns: ['nom_societe', 'profession', 'domaine_activite', 'description'],
    flags: ['fulltext']
)]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
#[UniqueEntity(fields: ['email'], message: 'Cette adresse email est déjà utilisée par une autre fiche.')]
#[UniqueEntity(fields: ['siret'], message: 'Ce numéro SIRET est déjà enregistré sur MusLinks.')]
class Professional
{
    public const STATUT_EN_ATTENTE   = 'EN_ATTENTE';
    public const STATUT_ACTIF        = 'ACTIF';
    public const STATUT_RENOUVELLEMENT = 'RENOUVELLEMENT';
    public const STATUT_RADIE        = 'RADIE';

    public const GENRE_HOMME = 'M';
    public const GENRE_FEMME = 'F';

    public const TYPE_PHYSIQUE  = 'PHYSIQUE';
    public const TYPE_ECOMMERCE = 'ECOMMERCE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom de société est obligatoire.')]
    #[Assert\Length(max: 255)]
    private ?string $nomSociete = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank(message: 'Le nom du responsable est obligatoire.', groups: ['physical'])]
    private ?string $nomResponsable = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank(message: 'Le prénom du responsable est obligatoire.', groups: ['physical'])]
    private ?string $prenomResponsable = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Le domaine d'activité est obligatoire.")]
    private ?string $domaineActivite = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(message: 'La profession est obligatoire.', groups: ['physical'])]
    private ?string $profession = null;

    #[ORM\Column(length: 20, unique: true, nullable: true)]
    #[Assert\NotBlank(message: 'Le numéro SIRET est obligatoire.', groups: ['physical'])]
    #[Assert\Regex(pattern: '/^[0-9]{14}$/', message: 'Le SIRET doit contenir exactement 14 chiffres.', groups: ['physical'])]
    private ?string $siret = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(message: "L'adresse est obligatoire.", groups: ['physical'])]
    private ?string $adresseRue = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\NotBlank(message: 'Le code postal est obligatoire.', groups: ['physical'])]
    #[Assert\Length(exactly: 5, exactMessage: 'Le code postal doit contenir exactement 5 chiffres.', groups: ['physical'])]
    private ?string $codePostal = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank(message: 'La ville est obligatoire.', groups: ['physical'])]
    private ?string $ville = null;

    #[ORM\Column(nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\NotBlank(message: 'Le téléphone est obligatoire.', groups: ['physical'])]
    private ?string $telephone = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: "L'email est obligatoire.")]
    #[Assert\Email(message: "L'adresse email n'est pas valide.")]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $siteWeb = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $siteEcommerce = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[Vich\UploadableField(mapping: 'professional_images', fileNameProperty: 'imageName')]
    private ?File $imageFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageName = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateInscription = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_EN_ATTENTE;

    #[ORM\Column(type: 'float')]
    private float $starsAverage = 0.0;

    #[ORM\Column]
    private int $totalAvis = 0;

    #[ORM\Column]
    private bool $isVisible = false;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $genre = null;

    #[ORM\Column(length: 5, nullable: true, options: ['default' => 'FR'])]
    private ?string $pays = 'FR';

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $loginToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $loginTokenExpires = null;

    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_PHYSIQUE;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $emailVerificationToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column]
    private bool $siretValide = false;

    #[ORM\OneToMany(mappedBy: 'professional', targetEntity: Review::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $reviews;

    public function __construct()
    {
        $this->reviews = new ArrayCollection();
        $this->dateInscription = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getNomSociete(): ?string { return $this->nomSociete; }
    public function setNomSociete(string $nomSociete): static { $this->nomSociete = $nomSociete; return $this; }

    public function getNomResponsable(): ?string { return $this->nomResponsable; }
    public function setNomResponsable(?string $nomResponsable): static { $this->nomResponsable = $nomResponsable; return $this; }

    public function getPrenomResponsable(): ?string { return $this->prenomResponsable; }
    public function setPrenomResponsable(?string $prenomResponsable): static { $this->prenomResponsable = $prenomResponsable; return $this; }

    public function getDomaineActivite(): ?string { return $this->domaineActivite; }
    public function setDomaineActivite(string $domaineActivite): static { $this->domaineActivite = $domaineActivite; return $this; }

    public function getProfession(): ?string { return $this->profession; }
    public function setProfession(?string $profession): static { $this->profession = $profession; return $this; }

    public function getSiret(): ?string { return $this->siret; }
    public function setSiret(?string $siret): static { $this->siret = $siret; return $this; }

    public function getAdresseRue(): ?string { return $this->adresseRue; }
    public function setAdresseRue(?string $adresseRue): static { $this->adresseRue = $adresseRue; return $this; }

    public function getCodePostal(): ?string { return $this->codePostal; }
    public function setCodePostal(?string $codePostal): static { $this->codePostal = $codePostal; return $this; }

    public function getVille(): ?string { return $this->ville; }
    public function setVille(?string $ville): static { $this->ville = $ville; return $this; }

    public function getLatitude(): ?float { return $this->latitude; }
    public function setLatitude(?float $latitude): static { $this->latitude = $latitude; return $this; }

    public function getLongitude(): ?float { return $this->longitude; }
    public function setLongitude(?float $longitude): static { $this->longitude = $longitude; return $this; }

    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $telephone): static { $this->telephone = $telephone; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getSiteWeb(): ?string { return $this->siteWeb; }
    public function setSiteWeb(?string $siteWeb): static { $this->siteWeb = $siteWeb; return $this; }

    public function getSiteEcommerce(): ?string { return $this->siteEcommerce; }
    public function setSiteEcommerce(?string $siteEcommerce): static { $this->siteEcommerce = $siteEcommerce; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getImageFile(): ?File { return $this->imageFile; }
    public function setImageFile(?File $imageFile): static
    {
        $this->imageFile = $imageFile;
        if ($imageFile !== null) {
            $this->updatedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function getImageName(): ?string { return $this->imageName; }
    public function setImageName(?string $imageName): static { $this->imageName = $imageName; return $this; }

    public function getDateInscription(): ?\DateTimeImmutable { return $this->dateInscription; }
    public function setDateInscription(\DateTimeImmutable $dateInscription): static { $this->dateInscription = $dateInscription; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }

    public function getStarsAverage(): float { return $this->starsAverage; }
    public function setStarsAverage(float $starsAverage): static { $this->starsAverage = $starsAverage; return $this; }

    public function getTotalAvis(): int { return $this->totalAvis; }
    public function setTotalAvis(int $totalAvis): static { $this->totalAvis = $totalAvis; return $this; }

    public function isVisible(): bool { return $this->isVisible; }
    public function setIsVisible(bool $isVisible): static { $this->isVisible = $isVisible; return $this; }

    public function getGenre(): ?string { return $this->genre; }
    public function setGenre(?string $genre): static { $this->genre = $genre; return $this; }

    public function getPays(): string { return $this->pays ?? 'FR'; }
    public function setPays(?string $pays): static { $this->pays = $pays; return $this; }

    public function getLoginToken(): ?string { return $this->loginToken; }
    public function setLoginToken(?string $loginToken): static { $this->loginToken = $loginToken; return $this; }

    public function getLoginTokenExpires(): ?\DateTimeImmutable { return $this->loginTokenExpires; }
    public function setLoginTokenExpires(?\DateTimeImmutable $loginTokenExpires): static { $this->loginTokenExpires = $loginTokenExpires; return $this; }

    public function isLoginTokenValid(string $token): bool
    {
        return $this->loginToken === $token
            && $this->loginTokenExpires !== null
            && $this->loginTokenExpires > new \DateTimeImmutable();
    }

    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }

    public function isEcommerce(): bool { return $this->type === self::TYPE_ECOMMERCE; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    public function getEmailVerificationToken(): ?string { return $this->emailVerificationToken; }
    public function setEmailVerificationToken(?string $emailVerificationToken): static { $this->emailVerificationToken = $emailVerificationToken; return $this; }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable { return $this->emailVerifiedAt; }
    public function setEmailVerifiedAt(?\DateTimeImmutable $emailVerifiedAt): static { $this->emailVerifiedAt = $emailVerifiedAt; return $this; }

    public function isEmailVerified(): bool { return $this->emailVerifiedAt !== null; }

    public function isSiretValide(): bool { return $this->siretValide; }
    public function setSiretValide(bool $siretValide): static { $this->siretValide = $siretValide; return $this; }

    public function getReviews(): Collection { return $this->reviews; }

    public function addReview(Review $review): static
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setProfessional($this);
        }
        return $this;
    }

    public function removeReview(Review $review): static
    {
        if ($this->reviews->removeElement($review)) {
            if ($review->getProfessional() === $this) {
                $review->setProfessional(null);
            }
        }
        return $this;
    }

    public function updateRatings(): void
    {
        $approvedReviews = $this->reviews->filter(fn(Review $r) => $r->isApproved());
        $total = $approvedReviews->count();
        if ($total === 0) {
            $this->starsAverage = 0.0;
            $this->totalAvis = 0;
            return;
        }
        $sum = array_sum($approvedReviews->map(fn(Review $r) => $r->getNote())->toArray());
        $this->starsAverage = round($sum / $total, 1);
        $this->totalAvis = $total;
    }

    public function getInitials(): string
    {
        $words = explode(' ', $this->nomSociete ?? '');
        $initials = '';
        foreach (array_slice($words, 0, 2) as $word) {
            if ($word !== '') {
                $initials .= mb_strtoupper(mb_substr($word, 0, 1));
            }
        }
        return $initials ?: 'ML';
    }

    public function getSiretFormatted(): string
    {
        $s = $this->siret ?? '';
        if (strlen($s) === 14) {
            return substr($s, 0, 3) . ' ' . substr($s, 3, 3) . ' ' . substr($s, 6, 3) . ' ' . substr($s, 9, 5);
        }
        return $s;
    }

    public function getAvatarColor(): string
    {
        $colors = ['059669', '0891b2', '7c3aed', 'db2777', 'ea580c', '65a30d'];
        return $colors[$this->id % count($colors)] ?? '059669';
    }
}
