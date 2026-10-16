import { NotificationService } from './notification.service';

describe('NotificationService', () => {
  it('adds and dismisses global notices', () => {
    const service = new NotificationService();
    service.showSuccess('Compra confirmada');
    expect(service.notices()).toHaveLength(1);
    expect(service.notices()[0].kind).toBe('success');
    service.close(service.notices()[0].id);
    expect(service.notices()).toHaveLength(0);
  });

  it('resolves confirmations according to the selected action', async () => {
    const service = new NotificationService();
    const answer = service.confirm({ title: 'Eliminar', message: '¿Continuar?', acceptLabel: 'Eliminar' });
    expect(service.confirmation()?.title).toBe('Eliminar');
    service.answerConfirmation(false);
    await expect(answer).resolves.toBe(false);
    expect(service.confirmation()).toBeNull();
  });
});
