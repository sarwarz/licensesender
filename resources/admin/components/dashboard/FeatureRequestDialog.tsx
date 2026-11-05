import { useState } from 'react';
import { Lightbulb } from 'lucide-react';
import { toast } from 'sonner';
import { apiRequest } from '@/api/client';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export function FeatureRequestDialog({ i18n }: { i18n: Record<string, string> }) {
  const [open, setOpen] = useState(false);
  const [title, setTitle] = useState('');
  const [message, setMessage] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!title.trim() || !message.trim()) {
      toast.error(i18n.featureRequestRequired || 'Please enter a title and description.');
      return;
    }
    setSubmitting(true);
    try {
      const result = await apiRequest<{ message?: string }>('feature-requests', {
        method: 'POST',
        body: JSON.stringify({ title: title.trim(), message: message.trim() }),
      });
      toast.success(result.message || i18n.featureRequestSuccess || 'Feature request submitted. Thank you!');
      setTitle('');
      setMessage('');
      setOpen(false);
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button variant="outline" size="sm" className="bg-white">
          <Lightbulb className="h-4 w-4 text-amber-500" />
          {i18n.suggestFeature || 'Suggest a feature'}
        </Button>
      </DialogTrigger>
      <DialogContent className="sm:max-w-lg">
        <form onSubmit={submit} className="space-y-4">
          <DialogHeader>
            <DialogTitle>{i18n.featureRequest || 'Feature Request'}</DialogTitle>
            <DialogDescription>
              {i18n.featureRequestDesc || 'Suggest a plugin improvement. Requests appear in the LicenseSender admin panel.'}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-1.5">
            <Label htmlFor="ls-feature-title">{i18n.featureRequestTitle || 'Title'}</Label>
            <Input
              id="ls-feature-title"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              placeholder={i18n.featureRequestTitlePlaceholder || 'Short summary of your idea'}
              maxLength={200}
              disabled={submitting}
              autoFocus
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="ls-feature-message">{i18n.featureRequestMessage || 'Details'}</Label>
            <textarea
              id="ls-feature-message"
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              placeholder={i18n.featureRequestMessagePlaceholder || 'Describe the problem or what you would like added…'}
              maxLength={5000}
              disabled={submitting}
              rows={5}
              className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
            />
            <p className="text-right text-xs text-slate-400">{message.length} / 5000</p>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setOpen(false)} disabled={submitting}>
              {i18n.cancel || 'Cancel'}
            </Button>
            <Button type="submit" disabled={submitting}>
              {submitting ? i18n.featureRequestSubmitting || 'Sending…' : i18n.featureRequestSubmit || 'Send request'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
