import { z } from 'zod';

export const patientSchema = z.object({
    patient_type_id: z.string().min(1, 'Patient type is required'),
    relationship_type_id: z.string().optional(),
    employee_no: z.string().optional(),
    insurance_no: z.string().optional(),
    dependent_no: z.string().optional(),
    full_name: z.string().min(1, 'Full name is required'),
    gender: z.string().optional(),
    date_of_birth: z.string().min(1, 'Date of birth is required'),
    phone: z.string().optional(),
    address: z.string().optional(),
    woreda: z.string().optional(),
    kebele: z.string().optional(),
    house_no: z.string().optional(),
}).superRefine((data, ctx) => {
    if (['Employee', 'Family'].includes(data.patient_type_id) && !data.employee_no) {
        ctx.addIssue({ code: 'custom', message: 'Employee number is required', path: ['employee_no'] });
    }
});

export type PatientFormData = z.infer<typeof patientSchema>;

export const visitAssignSchema = z.object({
    patient_id: z.string().min(1, 'Patient is required'),
    room_id: z.string().min(1, 'Room is required'),
    remarks: z.string().optional(),
});
