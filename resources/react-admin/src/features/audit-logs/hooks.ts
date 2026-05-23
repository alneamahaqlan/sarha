import { useQuery } from '@tanstack/react-query';
import { auditLogsApi, type AuditLogListParams } from './api/audit-logs.api';

const KEY = ['admin', 'audit-logs'] as const;

export function useAuditLogs(params: AuditLogListParams = {}) {
  return useQuery({
    queryKey: [...KEY, 'list', params],
    queryFn: () => auditLogsApi.list(params),
  });
}
