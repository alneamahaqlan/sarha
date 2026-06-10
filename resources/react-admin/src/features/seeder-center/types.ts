export type SeederRunStatus = 'queued' | 'running' | 'done' | 'failed';

export interface SeederBatchRun {
  id: number;
  batch: string;
  status: SeederRunStatus;
  message: string | null;
  rows_created: number | null;
  finished_at: string | null;
}

export interface SeederBatch {
  key: string;
  label_ar: string;
  label_en: string;
  desc_ar: string;
  desc_en: string;
  heavy: boolean;
  total_rows: number;
  hidden_rows: number;
  is_hidden: boolean;
  hideable: boolean;
  last_run: {
    status: SeederRunStatus;
    message: string | null;
    rows_created: number | null;
    finished_at: string | null;
  } | null;
}

export interface SeederConflict {
  child: string;
  parent: string;
  fk: string;
  count: number;
}
