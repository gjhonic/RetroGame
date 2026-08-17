<?php

namespace App\Service\OurGame;

use App\Dto\OurGame\OurGameRequest;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Собирает и валидирует OurGameRequest из тела JSON-запроса — общая логика для create()/update() в OurGameApiController. */
class OurGameRequestFactory
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @return array{0: OurGameRequest, 1: null}|array{0: null, 1: array<string, array<int, string>>}
     */
    public function fromJson(string $json): array
    {
        try {
            $dto = $this->serializer->deserialize($json, OurGameRequest::class, 'json');
        } catch (SerializerExceptionInterface) {
            return [null, ['_body' => ['Некорректное тело запроса.']]];
        }

        $violations = $this->validator->validate($dto);
        if (\count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()][] = $violation->getMessage();
            }

            return [null, $errors];
        }

        return [$dto, null];
    }
}
