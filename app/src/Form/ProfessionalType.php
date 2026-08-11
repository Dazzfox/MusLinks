<?php

namespace App\Form;

use App\Entity\Professional;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

class ProfessionalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('pays', ChoiceType::class, [
                'label' => 'Pays *',
                'choices' => [
                    '🇫🇷 France'     => 'FR',
                    '🇧🇪 Belgique'   => 'BE',
                    '🇨🇦 Canada'     => 'CA',
                    '🇨🇭 Suisse'     => 'CH',
                    '🇱🇺 Luxembourg' => 'LU',
                    '🇲🇦 Maroc'      => 'MA',
                    '🇩🇿 Algérie'    => 'DZ',
                    '🇹🇳 Tunisie'    => 'TN',
                    '🇱🇧 Liban'      => 'LB',
                ],
                'data' => 'FR',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('nomSociete', TextType::class, [
                'label' => 'Nom de la société *',
                'attr' => ['placeholder' => 'Ex : Cabinet Médical Santé+', 'class' => 'form-input'],
            ])
            ->add('nomResponsable', TextType::class, [
                'label' => 'Nom *',
                'attr' => ['placeholder' => 'Votre nom', 'class' => 'form-input'],
            ])
            ->add('prenomResponsable', TextType::class, [
                'label' => 'Prénom *',
                'attr' => ['placeholder' => 'Votre prénom', 'class' => 'form-input'],
            ])
            ->add('genre', ChoiceType::class, [
                'label' => 'Genre du responsable',
                'required' => false,
                'placeholder' => 'Non précisé',
                'choices' => [
                    'Homme' => 'M',
                    'Femme' => 'F',
                ],
                'attr' => ['class' => 'form-input'],
            ])
            ->add('domaineActivite', ChoiceType::class, [
                'label' => "Domaine d'activité *",
                'choices' => [
                    'Santé' => 'Santé',
                    'Droit & Conseil' => 'Droit & Conseil',
                    'Alimentation' => 'Alimentation',
                    'Bâtiment & Travaux' => 'Bâtiment & Travaux',
                    'Informatique & Tech' => 'Informatique & Tech',
                    'Beauté & Bien-être' => 'Beauté & Bien-être',
                    'Éducation & Formation' => 'Éducation & Formation',
                    'Finance & Assurance' => 'Finance & Assurance',
                    'Immobilier' => 'Immobilier',
                    'Mosquée & Religion' => 'Mosquée & Religion',
                    'Autre' => 'Autre',
                ],
                'placeholder' => 'Sélectionnez un domaine',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('profession', TextType::class, [
                'label' => 'Profession / Titre *',
                'attr' => ['placeholder' => 'Ex : Médecin généraliste, Avocat pénaliste…', 'class' => 'form-input'],
            ])
            ->add('siret', TextType::class, [
                'label' => 'Numéro d\'entreprise',
                'required' => false,
                'attr' => [
                    'placeholder' => '00000000000000',
                    'maxlength' => 20,
                    'class' => 'form-input font-mono',
                ],
            ])
            ->add('adresseRue', TextType::class, [
                'label' => 'Adresse *',
                'attr' => ['placeholder' => '12 rue de la Paix', 'class' => 'form-input'],
            ])
            ->add('codePostal', TextType::class, [
                'label' => 'Code postal *',
                'attr' => [
                    'placeholder' => '75001',
                    'maxlength' => 10,
                    'class' => 'form-input',
                ],
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville *',
                'attr' => ['placeholder' => 'Paris', 'class' => 'form-input'],
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone *',
                'attr' => [
                    'placeholder' => '06 00 00 00 00',
                    'class' => 'form-input',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email professionnel *',
                'attr' => ['placeholder' => 'contact@masociete.fr', 'class' => 'form-input'],
            ])
            ->add('siteWeb', UrlType::class, [
                'label' => 'Site web',
                'required' => false,
                'attr' => ['placeholder' => 'https://www.masociete.fr', 'class' => 'form-input'],
                'default_protocol' => 'https',
            ])
            ->add('siteEcommerce', UrlType::class, [
                'label' => 'Lien vers votre boutique en ligne',
                'required' => false,
                'attr' => [
                    'placeholder' => 'https://monshop.fr',
                    'class' => 'form-input',
                    'id' => 'site-ecommerce-input',
                ],
                'default_protocol' => 'https',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description de votre activité',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Décrivez votre activité, vos services, votre approche…',
                    'rows' => 5,
                    'class' => 'form-input',
                ],
            ])
            ->add('imageFile', VichImageType::class, [
                'label' => 'Photo / Logo',
                'required' => false,
                'allow_delete' => false,
                'download_uri' => false,
                'image_uri' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('latitude', HiddenType::class, ['required' => false])
            ->add('longitude', HiddenType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Professional::class,
        ]);
    }
}
