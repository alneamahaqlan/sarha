import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { seederCenterApi } from './api/seeder-center.api';

const KEY = ['admin', 'seeder-center'] as const;

export function useSeederInventory() {
  return useQuery({
    queryKey: [...KEY, 'inventory'],
    queryFn: () => seederCenterApi.inventory(),
  });
}

/** Fetch the FK-conflict list for a batch — only when a batch is targeted. */
export function useBatchConflicts(batch: string | null) {
  return useQuery({
    queryKey: [...KEY, 'conflicts', batch],
    queryFn: () => seederCenterApi.conflicts(batch as string),
    enabled: !!batch,
  });
}

export function useHideBatch() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (batch: string) => seederCenterApi.hide(batch),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useUnhideBatch() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (batch: string) => seederCenterApi.unhide(batch),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function usePurgeBatch() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ batch, force }: { batch: string; force?: boolean }) => seederCenterApi.purge(batch, force),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useReseedBatch() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (batch: string) => seederCenterApi.reseed(batch),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

/** Poll a heavy reseed run until it reaches a terminal state. */
export function useRunStatus(runId: number | null) {
  return useQuery({
    queryKey: [...KEY, 'run', runId],
    queryFn: () => seederCenterApi.runStatus(runId as number),
    enabled: !!runId,
    refetchInterval: (query) => {
      const status = query.state.data?.status;
      return status === 'done' || status === 'failed' ? false : 2000;
    },
  });
}
