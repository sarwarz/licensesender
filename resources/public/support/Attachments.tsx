import { useEffect, useRef, useState, type DragEvent, type ReactNode } from 'react';
import { Download, File, FileText, Film, Paperclip, UploadCloud, X } from 'lucide-react';
import { cn, fileSize, getConfig, t, validateFiles } from './lib';
import type { Attachment } from './types';

function iconFor(name: string, mime = '') {
  if (mime.startsWith('video/') || /\.(mp4|webm|mov|m4v)$/i.test(name)) return Film;
  if (mime === 'application/pdf' || /\.pdf$/i.test(name)) return FileText;
  return File;
}

/* ---------- Picking files (new ticket + reply) ---------- */

export function useFilePicker() {
  const [files, setFiles] = useState<File[]>([]);
  const [error, setError] = useState('');

  const add = (incoming: FileList | File[] | null) => {
    if (!incoming || !incoming.length) return;
    const result = validateFiles(files, Array.from(incoming));
    setFiles(result.files);
    setError(result.error);
  };

  const remove = (index: number) => {
    setFiles((current) => current.filter((_, i) => i !== index));
    setError('');
  };

  const reset = () => {
    setFiles([]);
    setError('');
  };

  return { files, error, add, remove, reset };
}

export function acceptAttr(): string {
  return getConfig().limits.extensions.map((ext) => `.${ext}`).join(',');
}

/** Wraps an area so files can be dropped anywhere on it. */
export function DropArea({ onFiles, children, className }: { onFiles: (files: FileList) => void; children: ReactNode; className?: string }) {
  const [over, setOver] = useState(false);
  const depth = useRef(0);

  const handle = (event: DragEvent, type: 'enter' | 'leave' | 'drop') => {
    if (!event.dataTransfer?.types?.includes('Files')) return;
    event.preventDefault();
    if (type === 'enter') depth.current += 1;
    if (type === 'leave') depth.current -= 1;
    if (type === 'drop') {
      depth.current = 0;
      onFiles(event.dataTransfer.files);
    }
    setOver(depth.current > 0);
  };

  return (
    <div
      className={cn('relative', className)}
      onDragEnter={(e) => handle(e, 'enter')}
      onDragLeave={(e) => handle(e, 'leave')}
      onDragOver={(e) => e.dataTransfer?.types?.includes('Files') && e.preventDefault()}
      onDrop={(e) => handle(e, 'drop')}
    >
      {children}
      {over ? (
        <div className="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-[inherit] border-2 border-dashed border-brand bg-brand/10 backdrop-blur-[1px]">
          <span className="flex items-center gap-2 rounded-full bg-surface px-4 py-2 text-sm font-medium text-brand shadow-pop">
            <UploadCloud className="h-4 w-4" />
            {t('dropFiles', 'Drop files to attach')}
          </span>
        </div>
      ) : null}
    </div>
  );
}

export function Dropzone({ onFiles }: { onFiles: (files: FileList | null) => void }) {
  const input = useRef<HTMLInputElement>(null);
  const { limits } = getConfig();
  return (
    <button
      type="button"
      onClick={() => input.current?.click()}
      className="flex w-full flex-col items-center justify-center gap-1.5 rounded-ctl border border-dashed border-line bg-subtle px-4 py-6 text-center transition-colors hover:border-brand/60 hover:bg-brand/5"
    >
      <span className="flex h-9 w-9 items-center justify-center rounded-full bg-surface text-muted shadow-sm ring-1 ring-line">
        <UploadCloud className="h-4 w-4" />
      </span>
      <span className="text-sm text-fg">
        <span className="font-medium text-brand">{t('clickToUpload', 'Upload images')}</span> {t('orDragDrop', 'or drag and drop')}
      </span>
      <span className="text-xs text-muted">{t('fileLimits', 'PNG, JPG, GIF or WEBP · up to %d MB each', limits.maxFileMb)}</span>
      <input
        ref={input}
        type="file"
        multiple
        accept={acceptAttr()}
        className="hidden"
        onChange={(e) => {
          onFiles(e.target.files);
          e.target.value = '';
        }}
      />
    </button>
  );
}

export function AttachButton({ onFiles }: { onFiles: (files: FileList | null) => void }) {
  const input = useRef<HTMLInputElement>(null);
  return (
    <>
      <button
        type="button"
        onClick={() => input.current?.click()}
        className="inline-flex h-9 items-center gap-1.5 rounded-ctl px-2.5 text-[13px] font-medium text-muted transition-colors hover:bg-fg/5 hover:text-fg"
        title={t('attachFiles', 'Attach files')}
      >
        <Paperclip className="h-4 w-4" />
        <span>{t('attach', 'Attach')}</span>
      </button>
      <input
        ref={input}
        type="file"
        multiple
        accept={acceptAttr()}
        className="hidden"
        onChange={(e) => {
          onFiles(e.target.files);
          e.target.value = '';
        }}
      />
    </>
  );
}

function LocalPreview({ file }: { file: File }) {
  const [url, setUrl] = useState('');
  useEffect(() => {
    if (!file.type.startsWith('image/')) return undefined;
    const objectUrl = URL.createObjectURL(file);
    setUrl(objectUrl);
    return () => URL.revokeObjectURL(objectUrl);
  }, [file]);

  if (url) return <img src={url} alt="" className="h-8 w-8 rounded-md object-cover" />;
  const Icon = iconFor(file.name, file.type);
  return (
    <span className="flex h-8 w-8 items-center justify-center rounded-md bg-fg/5 text-muted">
      <Icon className="h-4 w-4" />
    </span>
  );
}

export function FileChips({ files, onRemove }: { files: File[]; onRemove: (index: number) => void }) {
  if (!files.length) return null;
  return (
    <ul className="flex flex-wrap gap-2">
      {files.map((file, index) => (
        <li key={`${file.name}-${file.size}`} className="flex max-w-full animate-fade-in items-center gap-2 rounded-ctl border border-line bg-surface py-1 pl-1 pr-1.5 shadow-sm">
          <LocalPreview file={file} />
          <span className="min-w-0">
            <span className="block max-w-[180px] truncate text-[13px] font-medium text-fg">{file.name}</span>
            <span className="block text-[11px] text-muted">{fileSize(file.size)}</span>
          </span>
          <button
            type="button"
            onClick={() => onRemove(index)}
            className="rounded p-1 text-faint hover:bg-fg/5 hover:text-fg"
            aria-label={t('removeFile', 'Remove %s', file.name)}
          >
            <X className="h-3.5 w-3.5" />
          </button>
        </li>
      ))}
    </ul>
  );
}

/* ---------- Showing attachments on messages ---------- */

export function MessageAttachments({ items, mine, onPreview }: { items: Attachment[]; mine: boolean; onPreview: (a: Attachment) => void }) {
  if (!items.length) return null;
  const images = items.filter((a) => a.is_image && a.url);
  const others = items.filter((a) => !(a.is_image && a.url));

  return (
    <div className={cn('mt-2 flex flex-col gap-2', mine ? 'items-end' : 'items-start')}>
      {images.length ? (
        <div className={cn('flex flex-wrap gap-2', mine && 'justify-end')}>
          {images.map((a) => (
            <button
              key={a.id || a.url}
              type="button"
              onClick={() => onPreview(a)}
              className="group overflow-hidden rounded-ctl border border-line bg-subtle"
              title={a.name}
            >
              <img src={a.url} alt={a.name} loading="lazy" className="h-28 w-36 object-cover transition-transform duration-200 group-hover:scale-[1.03]" />
            </button>
          ))}
        </div>
      ) : null}
      {others.map((a) => {
        const Icon = iconFor(a.name, a.mime);
        return (
          <a
            key={a.id || a.download_url}
            href={a.download_url || a.url}
            target="_blank"
            rel="noopener noreferrer"
            className="flex max-w-full items-center gap-2.5 rounded-ctl border border-line bg-surface px-3 py-2 text-left no-underline shadow-sm transition-colors hover:bg-subtle"
          >
            <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-brand/10 text-brand">
              <Icon className="h-4 w-4" />
            </span>
            <span className="min-w-0">
              <span className="block max-w-[220px] truncate text-[13px] font-medium text-fg">{a.name}</span>
              {a.size ? <span className="block text-[11px] text-muted">{fileSize(a.size)}</span> : null}
            </span>
            <Download className="ml-1 h-4 w-4 shrink-0 text-faint" />
          </a>
        );
      })}
    </div>
  );
}

export function Lightbox({ item, onClose }: { item: Attachment | null; onClose: () => void }) {
  useEffect(() => {
    if (!item) return undefined;
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [item, onClose]);

  if (!item) return null;
  return (
    <div className="fixed inset-0 z-[100000] flex animate-fade-in flex-col bg-black/85 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-label={item.name} onClick={onClose}>
      <div className="flex items-center justify-between gap-3 text-white" onClick={(e) => e.stopPropagation()}>
        <span className="truncate text-sm font-medium">{item.name}</span>
        <div className="flex items-center gap-1">
          <a
            href={item.download_url || item.url}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex h-9 items-center gap-1.5 rounded-lg px-3 text-sm text-white/90 hover:bg-white/10"
          >
            <Download className="h-4 w-4" /> {t('download', 'Download')}
          </a>
          <button type="button" onClick={onClose} className="rounded-lg p-2 text-white/90 hover:bg-white/10" aria-label={t('close', 'Close')}>
            <X className="h-5 w-5" />
          </button>
        </div>
      </div>
      <div className="flex min-h-0 flex-1 items-center justify-center pt-3">
        <img src={item.url} alt={item.name} className="max-h-full max-w-full rounded-lg object-contain shadow-2xl" onClick={(e) => e.stopPropagation()} />
      </div>
    </div>
  );
}
