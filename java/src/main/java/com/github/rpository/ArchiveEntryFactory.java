package com.github.rpository;

import java.io.File;

import org.apache.commons.compress.archivers.ArchiveEntry;
import org.apache.commons.compress.archivers.ArchiveStreamFactory;
import org.apache.commons.compress.archivers.tar.TarArchiveEntry;
import org.apache.commons.compress.archivers.zip.ZipArchiveEntry;

public class ArchiveEntryFactory {
	private final String type;

	public ArchiveEntryFactory(String type) {
		this.type = type;
	}

	public ArchiveEntry newArchiveEntry(String entryName) {
		if (type.equals(ArchiveStreamFactory.TAR)) {
			return new TarArchiveEntry(entryName);
		} else if (type.equals(ArchiveStreamFactory.ZIP)) {
			return new ZipArchiveEntry(entryName);
		}
		return null;
	}

	public ArchiveEntry newArchiveEntry(File f, String entryName) {
		if (type.equals(ArchiveStreamFactory.TAR)) {
			return new TarArchiveEntry(f, entryName);
		} else if (type.equals(ArchiveStreamFactory.ZIP)) {
			return new ZipArchiveEntry(f, entryName);
		}
		return null;
	}
}
