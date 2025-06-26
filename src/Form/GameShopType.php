<?php

namespace App\Form;

use App\Entity\GameShop;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<GameShop>
 */
class GameShopType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('link_game_id')
            ->add('name')
            ->add('link')
            ->add('game_id')
            ->add('shop_id')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => GameShop::class,
        ]);
    }
}
