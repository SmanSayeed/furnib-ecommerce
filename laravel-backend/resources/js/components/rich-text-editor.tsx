import { EditorContent, useEditor  } from '@tiptap/react';
import type {Editor} from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
    Bold,
    Heading2,
    Heading3,
    Italic,
    List,
    ListOrdered,
    Quote,
    Redo2,
    Strikethrough,
    Undo2,
} from 'lucide-react';
import type { ComponentType } from 'react';

/**
 * Minimal TipTap rich-text editor for CMS page bodies. Emits HTML via onChange.
 * The HTML is re-sanitised server-side (HTMLPurifier) before storage, so this
 * editor is purely an authoring convenience — never the security boundary.
 */
export function RichTextEditor({
    value,
    onChange,
}: {
    value: string;
    onChange: (html: string) => void;
}) {
    const editor = useEditor({
        extensions: [StarterKit],
        content: value,
        // Inertia renders this on the client; avoid an SSR hydration mismatch.
        immediatelyRender: false,
        editorProps: {
            attributes: {
                class: 'prose prose-sm dark:prose-invert max-w-none min-h-[260px] px-3 py-3 focus:outline-none',
            },
        },
        onUpdate: ({ editor }) => onChange(editor.getHTML()),
    });

    if (!editor) {
        return null;
    }

    return (
        <div className="overflow-hidden rounded-md border border-input bg-background">
            <Toolbar editor={editor} />
            <EditorContent editor={editor} />
        </div>
    );
}

function Toolbar({ editor }: { editor: Editor }) {
    return (
        <div className="flex flex-wrap items-center gap-0.5 border-b border-input bg-muted/40 p-1">
            <ToolButton
                icon={Bold}
                label="Bold"
                active={editor.isActive('bold')}
                onClick={() => editor.chain().focus().toggleBold().run()}
            />
            <ToolButton
                icon={Italic}
                label="Italic"
                active={editor.isActive('italic')}
                onClick={() => editor.chain().focus().toggleItalic().run()}
            />
            <ToolButton
                icon={Strikethrough}
                label="Strikethrough"
                active={editor.isActive('strike')}
                onClick={() => editor.chain().focus().toggleStrike().run()}
            />
            <Divider />
            <ToolButton
                icon={Heading2}
                label="Heading 2"
                active={editor.isActive('heading', { level: 2 })}
                onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}
            />
            <ToolButton
                icon={Heading3}
                label="Heading 3"
                active={editor.isActive('heading', { level: 3 })}
                onClick={() => editor.chain().focus().toggleHeading({ level: 3 }).run()}
            />
            <Divider />
            <ToolButton
                icon={List}
                label="Bullet list"
                active={editor.isActive('bulletList')}
                onClick={() => editor.chain().focus().toggleBulletList().run()}
            />
            <ToolButton
                icon={ListOrdered}
                label="Numbered list"
                active={editor.isActive('orderedList')}
                onClick={() => editor.chain().focus().toggleOrderedList().run()}
            />
            <ToolButton
                icon={Quote}
                label="Quote"
                active={editor.isActive('blockquote')}
                onClick={() => editor.chain().focus().toggleBlockquote().run()}
            />
            <Divider />
            <ToolButton
                icon={Undo2}
                label="Undo"
                active={false}
                onClick={() => editor.chain().focus().undo().run()}
            />
            <ToolButton
                icon={Redo2}
                label="Redo"
                active={false}
                onClick={() => editor.chain().focus().redo().run()}
            />
        </div>
    );
}

function Divider() {
    return <span className="mx-1 h-5 w-px bg-border" aria-hidden="true" />;
}

function ToolButton({
    icon: Icon,
    label,
    active,
    onClick,
}: {
    icon: ComponentType<{ className?: string }>;
    label: string;
    active: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            aria-label={label}
            aria-pressed={active}
            onClick={onClick}
            className={`flex size-8 items-center justify-center rounded transition hover:bg-accent hover:text-accent-foreground ${
                active ? 'bg-accent text-accent-foreground' : 'text-muted-foreground'
            }`}
        >
            <Icon className="size-4" />
        </button>
    );
}
