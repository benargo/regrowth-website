/**
 * ManageDesktopClients Page
 *
 * Lists every Regrowth Desktop installation with its owner, status and last upload, and lets owners
 * (and users who may revoke any device) sign a device out.
 *
 * Props (via Inertia):
 * @prop {Object} devices - DesktopClientResource collection. `devices.data` lists devices, each with:
 *                            - id: string
 *                            - name: string
 *                            - owner: string
 *                            - isLinked: bool
 *                            - lastSeenAt: string|null (ISO)
 *                            - lastUploadAt: string|null (ISO)
 *                            - meta.canRevoke: bool
 *
 * Behaviour:
 * - A Revoke button is shown for linked devices the user may revoke; revoked devices show "Signed out".
 * - Below `md` the devices render as a stacked list instead of a table.
 */

import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import EmptyState from "@/Components/EmptyState";
import ToolNav, { ToolNavLink } from "@/Components/ToolNav";
import Icon from "@/Components/FontAwesome/Icon";
import RevokeButton from "@/Components/DesktopClients/RevokeButton";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/Table";
import formatDate from "@/Helpers/FormatDate";
import cn from "@/Helpers/cn";

/** A date as "08 Mar 2026", or "Never" when missing. */
function dateOrNever(value) {
    return value ? formatDate(value).medium : "Never";
}

/** Whether the device is still signed in. */
function Status({ isLinked }) {
    return (
        <span className={cn("inline-flex items-center gap-2 text-sm", isLinked ? "text-green-400" : "text-gray-400")}>
            <span aria-hidden="true" className={cn("size-2 rounded-full", isLinked ? "bg-green-400" : "bg-gray-600")} />
            {isLinked ? "Linked" : "Signed out"}
        </span>
    );
}

/** The Revoke button for a device, when the user may revoke it. */
function DeviceRevoke({ device }) {
    if (!device.meta.canRevoke || !device.isLinked) {
        return null;
    }

    return (
        <RevokeButton
            href={route("management.desktop-clients.destroy", { desktopClient: device.id })}
            label="Revoke"
            ariaLabel={`Revoke ${device.name}`}
            prompt={`Sign ${device.name} out?`}
            confirmLabel="Revoke"
        />
    );
}

export default function ManageDesktopClients({ devices }) {
    const rows = devices.data;

    return (
        <Master title="Desktop Clients">
            <SharedHeader title="Desktop Clients" backgroundClass="bg-officer-meeting" />
            <ToolNav>
                <div className="flex-initial space-x-4">
                    <ToolNavLink
                        href={route("management.dashboard")}
                        className="focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
                    >
                        <Icon icon="arrow-left" style="solid" className="mr-1 text-xs" />
                        Back to officers’ dashboard
                    </ToolNavLink>
                </div>
            </ToolNav>
            <PageContainer>
                <p className="mb-6 text-gray-400">
                    Every device signed in to the Regrowth Desktop app. Revoking a device signs it out until its owner
                    links it again.
                </p>

                {rows.length === 0 ? (
                    <EmptyState icon="desktop" message="No devices have been linked yet." />
                ) : (
                    <>
                        <div className="hidden md:block">
                            <Table>
                                <TableHead>
                                    <tr>
                                        <TableHeader scope="col" className="text-left">
                                            Device
                                        </TableHeader>
                                        <TableHeader scope="col" className="text-left">
                                            Owner
                                        </TableHeader>
                                        <TableHeader scope="col" className="text-left">
                                            Status
                                        </TableHeader>
                                        <TableHeader scope="col" className="text-left">
                                            Last seen
                                        </TableHeader>
                                        <TableHeader scope="col" className="text-left">
                                            Last upload
                                        </TableHeader>
                                        <TableHeader scope="col" className="text-right">
                                            <span className="sr-only">Actions</span>
                                        </TableHeader>
                                    </tr>
                                </TableHead>
                                <TableBody>
                                    {rows.map((device) => (
                                        <TableRow key={device.id} className={cn(!device.isLinked && "text-gray-400")}>
                                            <TableCell className="break-words">{device.name}</TableCell>
                                            <TableCell>{device.owner}</TableCell>
                                            <TableCell>
                                                <Status isLinked={device.isLinked} />
                                            </TableCell>
                                            <TableCell className="tabular-nums">
                                                {dateOrNever(device.lastSeenAt)}
                                            </TableCell>
                                            <TableCell className="tabular-nums">
                                                {dateOrNever(device.lastUploadAt)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end">
                                                    <DeviceRevoke device={device} />
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <ul className="divide-y divide-amber-600 border-y border-amber-600 md:hidden">
                            {rows.map((device) => (
                                <li key={device.id} className="flex flex-col gap-2 py-3">
                                    <div className="flex items-baseline justify-between gap-3">
                                        <span
                                            className={cn("min-w-0 break-words", !device.isLinked && "text-gray-400")}
                                        >
                                            {device.name}
                                        </span>
                                        <Status isLinked={device.isLinked} />
                                    </div>
                                    <p className="text-sm text-gray-400">
                                        {device.owner}. Last seen {dateOrNever(device.lastSeenAt)}, last upload{" "}
                                        {dateOrNever(device.lastUploadAt)}.
                                    </p>
                                    <DeviceRevoke device={device} />
                                </li>
                            ))}
                        </ul>
                    </>
                )}
            </PageContainer>
        </Master>
    );
}
