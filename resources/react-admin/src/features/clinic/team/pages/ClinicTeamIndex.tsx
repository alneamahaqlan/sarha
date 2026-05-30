import { useState } from 'react';
import { Activity, Edit3, KeyRound, Plus, Trash2, Users } from 'lucide-react';
import { Link } from 'react-router-dom';
import { toast } from 'sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Input } from '@/components/ui/input';
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { useAuth } from '@/app/providers/AuthProvider';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { RoleBadge } from '../components/RoleBadge';
import { TeamMemberFormDialog } from '../components/TeamMemberFormDialog';
import { PasswordRevealDialog } from '../components/PasswordRevealDialog';
import {
  useClinicTeam, useDeleteTeamMember, useRegenerateTeamMemberPassword,
} from '../hooks';
import type { ClinicTeamMember } from '../types';

/**
 * Owner-only "My Team" screen. Mirrors the pattern used by
 * ClinicDoctorsIndex (header + search + table + dialogs) so the
 * navigation feels native to anyone familiar with the rest of the
 * clinic panel.
 *
 * The owner row appears at the top with a "أنت — المدير" badge and
 * NO action buttons — per spec the owner cannot disable/remove
 * themselves from inside the panel.
 */
export function ClinicTeamIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { user } = useAuth();
  const [search, setSearch] = useState('');
  const { data: members = [], isLoading } = useClinicTeam(search.trim() || undefined);

  const [editing, setEditing] = useState<ClinicTeamMember | null>(null);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<ClinicTeamMember | null>(null);
  const [tempPassword, setTempPassword] = useState<{ pw: string; name: string } | null>(null);

  const del = useDeleteTeamMember();
  const regen = useRegenerateTeamMemberPassword();

  const fmtDate = (iso: string | null) =>
    iso ? new Date(iso).toLocaleDateString(locale === 'ar' ? 'ar-SA' : 'en-US') : '—';

  const handleDelete = async () => {
    if (!deleting) return;
    try {
      await del.mutateAsync(deleting.id);
      toast.success(t('clinic_team.removed'));
      setDeleting(null);
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
      setDeleting(null);
    }
  };

  const handleRegenerate = async (member: ClinicTeamMember) => {
    try {
      const result = await regen.mutateAsync(member.id);
      setTempPassword({ pw: result.temp_password, name: member.name });
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  // Synthetic "owner row" stitched on top of the API result so the
  // owner is visible in the same table per spec — without trying to
  // model the owner as a team_member row in the DB.
  const ownerRow = user?.guard === 'clinic' ? user.user : null;
  const activeCount = members.filter((m) => m.is_active).length;

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-xl sm:text-2xl font-semibold">{t('clinic_team.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_team.subtitle')}</p>
        </div>
        <div className="flex items-center gap-2 self-start sm:self-auto">
          <Badge variant="muted">
            <Users className="h-3 w-3" /> {t('clinic_team.active_count', { count: activeCount })}
          </Badge>
          <Link to="/app/clinic/team-activity">
            <Button variant="outline" size="sm">
              <Activity className="h-4 w-4" />
              {t('clinic_team.activity_link')}
            </Button>
          </Link>
          <Button onClick={() => setCreating(true)}>
            <Plus className="h-4 w-4" />
            {t('clinic_team.create')}
          </Button>
        </div>
      </div>

      {/* Search */}
      <div className="flex items-center gap-2">
        <Input
          placeholder={t('clinic_team.search_placeholder')}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="max-w-md"
        />
      </div>

      {/* Table */}
      <div className="rounded-md border border-[var(--color-border)] bg-white overflow-x-auto">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t('clinic_team.table.name')}</TableHead>
              <TableHead className="hidden sm:table-cell">{t('clinic_team.table.phone')}</TableHead>
              <TableHead>{t('clinic_team.table.role')}</TableHead>
              <TableHead className="hidden md:table-cell">{t('clinic_team.table.created_at')}</TableHead>
              <TableHead className="hidden lg:table-cell">{t('clinic_team.table.last_login')}</TableHead>
              <TableHead>{t('clinic_team.table.status')}</TableHead>
              <TableHead className="text-end">{t('clinic_team.table.actions')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {/* Owner row — non-removable per spec */}
            {ownerRow && (
              <TableRow className="bg-gold-whisper/30">
                <TableCell>
                  <div className="font-medium">{ownerRow.name}</div>
                  <div className="text-[10px] text-gold-deep">{t('clinic_team.you_owner')}</div>
                </TableCell>
                <TableCell dir="ltr" className="hidden sm:table-cell text-xs">{ownerRow.phone ?? '—'}</TableCell>
                <TableCell><RoleBadge role="owner" /></TableCell>
                <TableCell className="hidden md:table-cell text-xs text-[var(--color-muted-foreground)]">—</TableCell>
                <TableCell className="hidden lg:table-cell text-xs text-[var(--color-muted-foreground)]">—</TableCell>
                <TableCell><Badge variant="success">{t('clinic_team.status.active')}</Badge></TableCell>
                <TableCell className="text-end" />
              </TableRow>
            )}

            {isLoading && (
              <TableRow>
                <TableCell colSpan={7} className="py-8 text-center text-sm text-[var(--color-muted-foreground)]">
                  {t('common.loading')}
                </TableCell>
              </TableRow>
            )}

            {!isLoading && members.length === 0 && (
              <TableRow>
                <TableCell colSpan={7} className="py-8 text-center text-sm text-[var(--color-muted-foreground)]">
                  {t('clinic_team.empty')}
                </TableCell>
              </TableRow>
            )}

            {members.map((m) => (
              <TableRow key={m.id} className={!m.is_active ? 'opacity-60' : undefined}>
                <TableCell className="font-medium">{m.name}</TableCell>
                <TableCell dir="ltr" className="hidden sm:table-cell text-xs">{m.phone}</TableCell>
                <TableCell><RoleBadge role={m.role} color={m.role_color} /></TableCell>
                <TableCell className="hidden md:table-cell text-xs text-[var(--color-muted-foreground)]">{fmtDate(m.created_at)}</TableCell>
                <TableCell className="hidden lg:table-cell text-xs text-[var(--color-muted-foreground)]">{fmtDate(m.last_login_at)}</TableCell>
                <TableCell>
                  <Badge variant={m.is_active ? 'success' : 'muted'}>
                    {t(m.is_active ? 'clinic_team.status.active' : 'clinic_team.status.disabled')}
                  </Badge>
                </TableCell>
                <TableCell className="text-end">
                  <div className="flex justify-end gap-0.5">
                    <Link to={`/app/clinic/team-activity?actor_type=member&actor_id=${m.id}`}>
                      <Button variant="ghost" size="icon" aria-label={t('clinic_team.view_activity')} title={t('clinic_team.view_activity')}>
                        <Activity className="h-3.5 w-3.5" />
                      </Button>
                    </Link>
                    <Button
                      variant="ghost" size="icon"
                      onClick={() => handleRegenerate(m)}
                      disabled={regen.isPending}
                      aria-label={t('clinic_team.regen_password')}
                      title={t('clinic_team.regen_password')}
                    >
                      <KeyRound className="h-3.5 w-3.5" />
                    </Button>
                    <Button
                      variant="ghost" size="icon"
                      onClick={() => setEditing(m)}
                      aria-label={t('common.edit')}
                      title={t('common.edit')}
                    >
                      <Edit3 className="h-3.5 w-3.5" />
                    </Button>
                    <Button
                      variant="ghost" size="icon"
                      onClick={() => setDeleting(m)}
                      className="text-[var(--color-destructive)]"
                      aria-label={t('common.delete')}
                      title={t('common.delete')}
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      {/* Add / Edit dialog */}
      <TeamMemberFormDialog
        open={creating || editing !== null}
        member={editing}
        onClose={() => { setCreating(false); setEditing(null); }}
        onCreatedWithPassword={(pw, member) => setTempPassword({ pw, name: member.name })}
      />

      {/* One-time password reveal */}
      <PasswordRevealDialog
        open={tempPassword !== null}
        password={tempPassword?.pw ?? null}
        memberName={tempPassword?.name ?? ''}
        onClose={() => setTempPassword(null)}
      />

      {/* Delete confirmation */}
      <AlertDialog open={deleting !== null} onOpenChange={(o) => !o && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('clinic_team.delete_confirm_title')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('clinic_team.delete_confirm_body', { name: deleting?.name ?? '' })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction onClick={handleDelete} disabled={del.isPending}>
              {t('common.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
