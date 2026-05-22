import { useEditor, EditorContent, type Editor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import {
  Bold,
  Italic,
  List,
  ListOrdered,
  Heading2,
  Heading3,
  Quote,
  Link as LinkIcon,
  Undo,
  Redo,
  Strikethrough,
  Code,
} from 'lucide-react';
import { useEffect } from 'react';
import { cn } from '@/lib/utils';

interface RichEditorProps {
  value: string;
  onChange: (html: string) => void;
  placeholder?: string;
  disabled?: boolean;
  className?: string;
  minHeight?: number;
}

interface ToolButtonProps {
  onClick: () => void;
  active?: boolean;
  disabled?: boolean;
  label: string;
  children: React.ReactNode;
}

function ToolButton({ onClick, active, disabled, label, children }: ToolButtonProps) {
  return (
    <button
      type="button"
      aria-label={label}
      title={label}
      onClick={onClick}
      disabled={disabled}
      className={cn(
        'inline-flex h-8 w-8 items-center justify-center rounded text-[var(--color-foreground)] transition-colors',
        active ? 'bg-[var(--color-primary)] text-white' : 'hover:bg-[var(--color-muted)]',
        'disabled:opacity-40 disabled:pointer-events-none',
      )}
    >
      {children}
    </button>
  );
}

function Toolbar({ editor }: { editor: Editor }) {
  const promptLink = () => {
    const previous = editor.getAttributes('link').href as string | undefined;
    const url = window.prompt('URL', previous ?? 'https://');
    if (url === null) return;
    if (url === '') {
      editor.chain().focus().extendMarkRange('link').unsetLink().run();
      return;
    }
    editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
  };

  return (
    <div className="flex flex-wrap items-center gap-0.5 border-b border-[var(--color-border)] bg-[var(--color-muted)] px-2 py-1">
      <ToolButton label="Bold" onClick={() => editor.chain().focus().toggleBold().run()} active={editor.isActive('bold')}>
        <Bold className="h-3.5 w-3.5" />
      </ToolButton>
      <ToolButton label="Italic" onClick={() => editor.chain().focus().toggleItalic().run()} active={editor.isActive('italic')}>
        <Italic className="h-3.5 w-3.5" />
      </ToolButton>
      <ToolButton label="Strikethrough" onClick={() => editor.chain().focus().toggleStrike().run()} active={editor.isActive('strike')}>
        <Strikethrough className="h-3.5 w-3.5" />
      </ToolButton>
      <ToolButton label="Inline code" onClick={() => editor.chain().focus().toggleCode().run()} active={editor.isActive('code')}>
        <Code className="h-3.5 w-3.5" />
      </ToolButton>
      <div className="mx-1 h-5 w-px bg-[var(--color-border)]" />
      <ToolButton label="Heading 2" onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()} active={editor.isActive('heading', { level: 2 })}>
        <Heading2 className="h-3.5 w-3.5" />
      </ToolButton>
      <ToolButton label="Heading 3" onClick={() => editor.chain().focus().toggleHeading({ level: 3 }).run()} active={editor.isActive('heading', { level: 3 })}>
        <Heading3 className="h-3.5 w-3.5" />
      </ToolButton>
      <ToolButton label="Bullet list" onClick={() => editor.chain().focus().toggleBulletList().run()} active={editor.isActive('bulletList')}>
        <List className="h-3.5 w-3.5" />
      </ToolButton>
      <ToolButton label="Ordered list" onClick={() => editor.chain().focus().toggleOrderedList().run()} active={editor.isActive('orderedList')}>
        <ListOrdered className="h-3.5 w-3.5" />
      </ToolButton>
      <ToolButton label="Quote" onClick={() => editor.chain().focus().toggleBlockquote().run()} active={editor.isActive('blockquote')}>
        <Quote className="h-3.5 w-3.5" />
      </ToolButton>
      <ToolButton label="Link" onClick={promptLink} active={editor.isActive('link')}>
        <LinkIcon className="h-3.5 w-3.5" />
      </ToolButton>
      <div className="mx-1 h-5 w-px bg-[var(--color-border)]" />
      <ToolButton label="Undo" onClick={() => editor.chain().focus().undo().run()} disabled={!editor.can().undo()}>
        <Undo className="h-3.5 w-3.5" />
      </ToolButton>
      <ToolButton label="Redo" onClick={() => editor.chain().focus().redo().run()} disabled={!editor.can().redo()}>
        <Redo className="h-3.5 w-3.5" />
      </ToolButton>
    </div>
  );
}

export function RichEditor({ value, onChange, placeholder, disabled, className, minHeight = 220 }: RichEditorProps) {
  const editor = useEditor({
    extensions: [
      StarterKit,
      Link.configure({ openOnClick: false, autolink: true, HTMLAttributes: { rel: 'noopener noreferrer', target: '_blank' } }),
    ],
    content: value,
    editable: !disabled,
    onUpdate: ({ editor: ed }) => {
      const html = ed.getHTML();
      onChange(html === '<p></p>' ? '' : html);
    },
    editorProps: {
      attributes: {
        class: 'prose prose-sm max-w-none px-4 py-3 focus:outline-none',
        style: `min-height: ${minHeight}px`,
        'data-placeholder': placeholder ?? '',
      },
    },
  });

  useEffect(() => {
    if (!editor) return;
    const current = editor.getHTML();
    const incoming = value || '';
    const normalized = incoming || '<p></p>';
    if (normalized !== current && !editor.isFocused) {
      editor.commands.setContent(normalized, { emitUpdate: false });
    }
  }, [editor, value]);

  useEffect(() => {
    if (editor) editor.setEditable(!disabled);
  }, [editor, disabled]);

  if (!editor) return null;

  return (
    <div className={cn('rounded-md border border-[var(--color-border)] bg-white', className)}>
      <Toolbar editor={editor} />
      <EditorContent editor={editor} />
    </div>
  );
}
