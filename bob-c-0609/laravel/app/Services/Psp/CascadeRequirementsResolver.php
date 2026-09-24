<?php

namespace App\Services\Psp;

use App\Contracts\PspAdaptorInterface;
use App\DTO\PspPaymentRequest;
use App\Services\Psp\Contracts\PspConverterInterface;
use RuntimeException;

final class CascadeRequirementsResolver
{
    public function __construct(private PspAdapterRegistry $registry)
    {
    }

    public function resolve(array $orderedPspCodes): array
    {
        $fields = [];
        foreach ($orderedPspCodes as $index => $code) {
            $converter = $this->converterFor((string) $code);
            foreach ($converter->requiredFields() as $field) {
                $fields[$field] ??= [
                    'field' => $field,
                    'required_by' => [],
                ];
                $fields[$field]['required_by'][] = [
                    'psp_code' => $converter->pspCode(),
                    'cascade_index' => $index + 1,
                    'psp_field' => $converter->createPayloadFieldMap()[$field] ?? null,
                ];
            }
        }

        ksort($fields);

        return [
            'cascade' => array_values(array_map(static fn ($code): string => strtoupper((string) $code), $orderedPspCodes)),
            'required_fields' => array_values($fields),
        ];
    }

    public function validatePreCollect(PspPaymentRequest $request, array $orderedPspCodes): array
    {
        $missing = [];
        foreach ($this->resolve($orderedPspCodes)['required_fields'] as $row) {
            $value = $request->get($row['field']);
            if ($value === null || $value === '') {
                $missing[] = [
                    'field' => $row['field'],
                    'required_by' => $row['required_by'],
                    'cascade_reason' => 'missing_required_field:' . $row['field'],
                ];
            }
        }

        return [
            'ok' => $missing === [],
            'missing_fields' => $missing,
        ];
    }

    public function fieldMapFor(string $pspCode): array
    {
        return $this->converterFor($pspCode)->createPayloadFieldMap();
    }

    public function converterFor(string $pspCode): PspConverterInterface
    {
        return $this->converterFrom($this->registry->get($pspCode));
    }

    private function converterFrom(PspAdaptorInterface $adaptor): PspConverterInterface
    {
        $ref = new \ReflectionClass($adaptor);
        do {
            if ($ref->hasProperty('converter')) {
                $property = $ref->getProperty('converter');
                $property->setAccessible(true);
                $converter = $property->getValue($adaptor);
                if ($converter instanceof PspConverterInterface) {
                    return $converter;
                }
            }
            $ref = $ref->getParentClass();
        } while ($ref !== false);

        throw new RuntimeException('Unable to inspect PSP converter for cascade requirements.');
    }
}
