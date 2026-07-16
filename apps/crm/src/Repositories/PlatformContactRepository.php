<?php

declare(strict_types=1);

final class PlatformContactRepository
{
    public static function metrics(): array
    {
        PlatformClientRepository::syncMissingFromEmpresas();
        PlatformClientRepository::syncLeadStatusClients();
        PlatformLeadRepository::ensureTable();
        PlatformClientRepository::ensureTable();

        $leadMetrics = PlatformLeadRepository::metrics();
        $clientMetrics = PlatformClientRepository::metrics();

        return [
            'new' => (int) $leadMetrics['new'],
            'qualified' => (int) $leadMetrics['qualified'] + (int) $clientMetrics['qualified'],
            'customers' => self::convertedLeadsWithoutClient() + (int) $clientMetrics['customer'],
            'lost' => (int) $leadMetrics['lost'] + (int) $clientMetrics['lost'],
        ];
    }

    public static function all(string $query = '', string $status = '', string $type = ''): array
    {
        PlatformClientRepository::syncMissingFromEmpresas();
        PlatformClientRepository::syncLeadStatusClients();

        $contacts = [];
        $leadStatus = $status === 'LEAD' ? '' : self::leadStatus($status);
        $clientStatus = self::clientStatus($status);

        if (($type === '' || $type === 'lead') && ($status === '' || $status === 'LEAD' || $leadStatus !== '')) {
            foreach (PlatformLeadRepository::all($query, $leadStatus) as $lead) {
                if (!empty($lead['client_id']) && ($lead['client_status'] ?? '') !== 'LEAD') {
                    continue;
                }

                if ($lead['status'] === 'CONVERTED' && !empty($lead['client_id'])) {
                    continue;
                }

                $contacts[] = [
                    'type' => 'lead',
                    'id' => $lead['id'],
                    'company_name' => $lead['company_name'] ?: 'Sin gimnasio',
                    'contact_name' => $lead['contact_name'],
                    'email' => $lead['email'],
                    'phone' => $lead['phone'],
                    'status' => $lead['status'],
                    'status_label' => platform_lead_status_label($lead['status']),
                    'status_class' => strtolower(str_replace('_', '-', (string) $lead['status'])),
                    'notes' => $lead['message'],
                    'source_label' => 'Lead web',
                    'created_at' => $lead['created_at'],
                    'updated_at' => $lead['updated_at'],
                    'raw' => $lead,
                ];
            }
        }

        if (($type === '' || $type === 'client') && ($status === '' || $clientStatus !== '')) {
            foreach (PlatformClientRepository::all($query, $clientStatus, true) as $client) {
                if ($client['status'] === 'LEAD') {
                    continue;
                }

                $empresa = EmpresaRepository::findByClient((string) $client['id']);
                $contacts[] = [
                    'type' => 'client',
                    'id' => $client['id'],
                    'company_name' => $client['company_name'],
                    'contact_name' => $client['contact_name'],
                    'email' => $client['email'],
                    'phone' => $client['phone'],
                    'status' => $client['status'],
                    'status_label' => platform_client_status_label($client['status']),
                    'status_class' => strtolower((string) $client['status']),
                    'notes' => $client['notes'],
                    'source_label' => 'Cliente CRM',
                    'created_at' => $client['created_at'],
                    'updated_at' => $client['updated_at'],
                    'empresa' => $empresa,
                    'raw' => $client,
                ];
            }
        }

        usort($contacts, static function (array $a, array $b): int {
            $timeA = strtotime((string) ($a['updated_at'] ?: $a['created_at'])) ?: 0;
            $timeB = strtotime((string) ($b['updated_at'] ?: $b['created_at'])) ?: 0;

            return $timeB <=> $timeA;
        });

        return $contacts;
    }

    private static function leadStatus(string $status): string
    {
        return in_array($status, ['NEW', 'CONTACTED', 'QUALIFIED', 'CONVERTED', 'LOST'], true) ? $status : '';
    }

    private static function clientStatus(string $status): string
    {
        return in_array($status, ['LEAD', 'QUALIFIED', 'CUSTOMER', 'LOST'], true) ? $status : '';
    }

    private static function convertedLeadsWithoutClient(): int
    {
        $stmt = Database::connection()->query(
            'SELECT COUNT(*)
             FROM platform_leads
             WHERE status = "CONVERTED"
               AND (client_id IS NULL OR client_id = "")'
        );

        return (int) $stmt->fetchColumn();
    }
}
