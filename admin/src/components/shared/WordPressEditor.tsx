import { useEffect, useRef } from 'react';
import { Label } from '@/components/ui/label';

export const ACTIVATION_GUIDE_EDITOR_ID = 'ls-activation-guide-content';

interface WordPressEditorProps {
  label?: string;
  value: string;
  onChange: (value: string) => void;
  helpText?: string;
}

export function getWordPressEditorContent(editorId: string = ACTIVATION_GUIDE_EDITOR_ID): string {
  const editor = window.tinymce?.get(editorId);
  if (editor && !editor.removed) {
    return editor.getContent();
  }

  const textarea = document.getElementById(editorId) as HTMLTextAreaElement | null;
  return textarea?.value ?? '';
}

export function removeWordPressEditor(editorId: string = ACTIVATION_GUIDE_EDITOR_ID) {
  if (window.wp?.editor) {
    window.wp.editor.remove(editorId);
  }
}

export function WordPressEditor({
  label = 'Activation Guide Content',
  value,
  onChange,
  helpText,
}: WordPressEditorProps) {
  const onChangeRef = useRef(onChange);
  onChangeRef.current = onChange;

  useEffect(() => {
    if (!window.wp?.editor) {
      return;
    }

    const initEditor = () => {
      const textarea = document.getElementById(ACTIVATION_GUIDE_EDITOR_ID) as HTMLTextAreaElement | null;
      if (!textarea) {
        return;
      }

      removeWordPressEditor();

      textarea.value = value;
      window.wp!.editor!.initialize(ACTIVATION_GUIDE_EDITOR_ID, {
        tinymce: {
          wpautop: true,
          plugins: 'charmap,colorpicker,hr,lists,media,paste,tabfocus,textcolor,wordpress,wpautoresize,wpeditimage,wpgallery,wplink,wpdialogs,wptextpattern',
          toolbar1:
            'formatselect,bold,italic,underline,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,wp_more,spellchecker,fullscreen,wp_adv',
          toolbar2:
            'strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help',
          height: 300,
          setup: (editor: { on: (event: string, callback: () => void) => void; getContent: () => string }) => {
            const sync = () => onChangeRef.current(editor.getContent());
            editor.on('change', sync);
            editor.on('keyup', sync);
            editor.on('SetContent', sync);
          },
        },
        quicktags: true,
        mediaButtons: true,
      });
    };

    const timer = window.setTimeout(initEditor, 150);

    return () => {
      window.clearTimeout(timer);
      removeWordPressEditor();
    };
  }, []);

  useEffect(() => {
    const editor = window.tinymce?.get(ACTIVATION_GUIDE_EDITOR_ID);
    if (!editor || editor.removed) {
      const textarea = document.getElementById(ACTIVATION_GUIDE_EDITOR_ID) as HTMLTextAreaElement | null;
      if (textarea && textarea.value !== value) {
        textarea.value = value;
      }
      return;
    }

    if (editor.getContent() !== value) {
      editor.setContent(value || '');
    }
  }, [value]);

  if (!window.wp?.editor) {
    return (
      <div className="space-y-2">
        <Label htmlFor={ACTIVATION_GUIDE_EDITOR_ID}>{label}</Label>
        <textarea
          id={ACTIVATION_GUIDE_EDITOR_ID}
          className="flex min-h-[220px] w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm"
          value={value}
          onChange={(e) => onChange(e.target.value)}
        />
        {helpText ? <p className="text-xs text-muted-foreground">{helpText}</p> : null}
      </div>
    );
  }

  return (
    <div className="space-y-2 ls-wp-editor-field">
      <Label htmlFor={ACTIVATION_GUIDE_EDITOR_ID}>{label}</Label>
      <textarea id={ACTIVATION_GUIDE_EDITOR_ID} defaultValue={value} className="w-full" />
      {helpText ? <p className="text-xs text-muted-foreground">{helpText}</p> : null}
    </div>
  );
}
